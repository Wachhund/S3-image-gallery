<?php

declare(strict_types=1);

namespace S3Gallery\Service;

use Aws\S3\S3Client;
use PDO;

final class UploadService
{
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    private const THUMB_PREFIX = 'thumbs/';
    private const THUMB_WIDTH = 300;
    private const JPEG_QUALITY = 85;

    public function __construct(
        private readonly S3Client $s3,
        private readonly PDO $db,
        private readonly string $bucket,
        private readonly int $maxFileSize = 20 * 1024 * 1024,
    ) {}

    public function validateFile(array $uploadedFile): ?string
    {
        if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return match ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei ist zu groß.',
                UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewählt.',
                default => 'Upload-Fehler.',
            };
        }

        if (($uploadedFile['size'] ?? 0) > $this->maxFileSize) {
            $maxMb = (int) ($this->maxFileSize / 1024 / 1024);
            return "Datei überschreitet das Limit von {$maxMb} MB.";
        }

        $tmpPath = $uploadedFile['tmp_name'] ?? '';
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return 'Ungültige Upload-Datei.';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            return 'Nur Bilddateien (JPEG, PNG, WebP, GIF) sind erlaubt.';
        }

        return null;
    }

    public function processUpload(array $uploadedFile, int $dirId, string $dirPath): array
    {
        $tmpPath = $uploadedFile['tmp_name'];
        $originalName = basename($uploadedFile['name']);
        $s3Key = $dirPath . '/' . $originalName;

        $existingName = $this->imageExists($s3Key);
        if ($existingName) {
            $s3Key = $this->makeUniqueName($s3Key);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        $this->s3->putObject([
            'Bucket' => $this->bucket,
            'Key' => $s3Key,
            'SourceFile' => $tmpPath,
            'ContentType' => $mime,
        ]);

        $imageId = $this->catalogImage($s3Key, $uploadedFile, $dirId);

        $thumbPath = null;
        try {
            $thumbPath = $this->generateAndUploadThumbnail($tmpPath, $s3Key, $imageId);
        } catch (\Throwable $e) {
            // Thumbnail failure is non-fatal
        }

        return [
            'image_id' => $imageId,
            's3_key' => $s3Key,
            'thumb_path' => $thumbPath,
        ];
    }

    private function imageExists(string $name): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM images WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        return $stmt->fetchColumn() !== false;
    }

    private function makeUniqueName(string $s3Key): string
    {
        $info = pathinfo($s3Key);
        $base = $info['dirname'] . '/' . $info['filename'];
        $ext = $info['extension'] ?? 'jpg';
        $suffix = 1;

        do {
            $candidate = "{$base}_{$suffix}.{$ext}";
            $suffix++;
        } while ($this->imageExists($candidate));

        return $candidate;
    }

    private function catalogImage(string $s3Key, array $uploadedFile, int $dirId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO images (name, size, time, hash, dir_id)
             VALUES (:name, :size, :time, :hash, :dir_id)'
        );
        $stmt->execute([
            'name' => $s3Key,
            'size' => (int) ($uploadedFile['size'] ?? 0),
            'time' => time(),
            'hash' => md5_file($uploadedFile['tmp_name']) ?: '',
            'dir_id' => $dirId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function generateAndUploadThumbnail(string $localPath, string $originalKey, int $imageId): string
    {
        $extension = strtolower(pathinfo($originalKey, PATHINFO_EXTENSION));

        $gdImage = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($localPath),
            'png' => @imagecreatefrompng($localPath),
            'webp' => @imagecreatefromwebp($localPath),
            'gif' => @imagecreatefromgif($localPath),
            default => false,
        };

        if ($gdImage === false) {
            throw new \RuntimeException('Failed to load image for thumbnail.');
        }

        $exif = @exif_read_data($localPath);
        if ($exif !== false && isset($exif['Orientation'])) {
            $gdImage = match ((int) $exif['Orientation']) {
                3 => imagerotate($gdImage, 180, 0) ?: $gdImage,
                6 => imagerotate($gdImage, -90, 0) ?: $gdImage,
                8 => imagerotate($gdImage, 90, 0) ?: $gdImage,
                default => $gdImage,
            };
        }

        $origWidth = imagesx($gdImage);
        $origHeight = imagesy($gdImage);

        if ($origWidth <= self::THUMB_WIDTH) {
            $newWidth = $origWidth;
            $newHeight = $origHeight;
        } else {
            $newWidth = self::THUMB_WIDTH;
            $newHeight = (int) floor($origHeight * (self::THUMB_WIDTH / $origWidth));
        }

        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumb, $gdImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        imagedestroy($gdImage);

        $tempThumb = tempnam(sys_get_temp_dir(), 's3g_upload_thumb_');
        imagejpeg($thumb, $tempThumb, self::JPEG_QUALITY);
        imagedestroy($thumb);

        $thumbKey = self::THUMB_PREFIX . $originalKey;

        try {
            $this->s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $thumbKey,
                'SourceFile' => $tempThumb,
                'ContentType' => 'image/jpeg',
            ]);

            $stmt = $this->db->prepare(
                'INSERT INTO thumbs (name, image_id) VALUES (:name, :image_id)'
            );
            $stmt->execute(['name' => $thumbKey, 'image_id' => $imageId]);
        } finally {
            if (file_exists($tempThumb)) {
                @unlink($tempThumb);
            }
        }

        return $thumbKey;
    }
}
