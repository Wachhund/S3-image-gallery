<?php

declare(strict_types=1);

namespace S3Gallery\Service;

use Aws\S3\S3Client;
use PDO;

final class ThumbnailGenerator
{
    private const THUMB_PREFIX = 'thumbs/';
    private const JPEG_QUALITY = 85;

    public function __construct(
        private readonly S3Client $s3,
        private readonly PDO $db,
        private readonly string $bucket,
        private readonly int $thumbWidth = 300,
    ) {}

    public function generate(): int
    {
        $images = $this->findImagesWithoutThumbnails();
        $total = count($images);

        if ($total === 0) {
            echo "No images without thumbnails found.\n";
            return 0;
        }

        echo "Generating thumbnails for {$total} images...\n";

        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($images as $i => $image) {
            $num = $i + 1;
            $key = $image['name'];

            try {
                $thumbPath = self::THUMB_PREFIX . $key;

                if ($this->thumbnailExistsInS3($thumbPath)) {
                    $this->insertThumbRecord($thumbPath, (int) $image['id']);
                    $skipped++;
                    echo "[{$num}/{$total}] {$key} → S3 thumb exists, DB record added\n";
                    continue;
                }

                $tempSource = $this->downloadFromS3($key);

                try {
                    $tempThumb = $this->createThumbnail($tempSource, $key);

                    try {
                        $this->uploadToS3($tempThumb, $thumbPath);
                        $this->insertThumbRecord($thumbPath, (int) $image['id']);
                        $created++;
                        echo "[{$num}/{$total}] {$key} → created\n";
                    } finally {
                        $this->cleanup($tempThumb);
                    }
                } finally {
                    $this->cleanup($tempSource);
                }
            } catch (\Throwable $e) {
                $errors++;
                echo "[{$num}/{$total}] {$key} → ERROR: {$e->getMessage()}\n";
            }
        }

        echo "\nDone. {$total} processed, {$created} created, {$skipped} existing, {$errors} errors.\n";

        return $errors > 0 ? 1 : 0;
    }

    private function findImagesWithoutThumbnails(): array
    {
        $stmt = $this->db->query(
            'SELECT i.id, i.name FROM images i
             LEFT JOIN thumbs t ON i.id = t.image_id
             WHERE t.id IS NULL
             ORDER BY i.id ASC'
        );

        return $stmt->fetchAll();
    }

    private function thumbnailExistsInS3(string $thumbPath): bool
    {
        try {
            $this->s3->headObject([
                'Bucket' => $this->bucket,
                'Key' => $thumbPath,
            ]);
            return true;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    private function downloadFromS3(string $key): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 's3g_src_');

        $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SaveAs' => $tempFile,
        ]);

        return $tempFile;
    }

    private function createThumbnail(string $sourcePath, string $originalKey): string
    {
        $gdImage = $this->loadImage($sourcePath, $originalKey);

        if ($gdImage === false) {
            throw new \RuntimeException("Failed to load image: {$originalKey}");
        }

        $gdImage = $this->applyExifRotation($gdImage, $sourcePath);

        $origWidth = imagesx($gdImage);
        $origHeight = imagesy($gdImage);

        if ($origWidth <= $this->thumbWidth) {
            $newWidth = $origWidth;
            $newHeight = $origHeight;
        } else {
            $newWidth = $this->thumbWidth;
            $newHeight = (int) floor($origHeight * ($this->thumbWidth / $origWidth));
        }

        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumb, $gdImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        imagedestroy($gdImage);

        $tempFile = tempnam(sys_get_temp_dir(), 's3g_thumb_');
        imagejpeg($thumb, $tempFile, self::JPEG_QUALITY);
        imagedestroy($thumb);

        return $tempFile;
    }

    private function loadImage(string $path, string $key): \GdImage|false
    {
        $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
            'gif' => @imagecreatefromgif($path),
            default => false,
        };
    }

    private function applyExifRotation(\GdImage $image, string $path): \GdImage
    {
        $exif = @exif_read_data($path);

        if ($exif === false || !isset($exif['Orientation'])) {
            return $image;
        }

        return match ((int) $exif['Orientation']) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    private function uploadToS3(string $localPath, string $s3Key): void
    {
        $this->s3->putObject([
            'Bucket' => $this->bucket,
            'Key' => $s3Key,
            'SourceFile' => $localPath,
            'ContentType' => 'image/jpeg',
        ]);
    }

    private function insertThumbRecord(string $thumbPath, int $imageId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO thumbs (name, image_id) VALUES (:name, :image_id)'
        );

        $stmt->execute([
            'name' => $thumbPath,
            'image_id' => $imageId,
        ]);
    }

    private function cleanup(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
