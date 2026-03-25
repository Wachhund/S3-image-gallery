<?php

declare(strict_types=1);

namespace S3Gallery\Service;

use Aws\S3\S3Client;
use PDO;

final class BucketScanner
{
    private const DEFAULT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** @var array<string, int> */
    private array $dirCache = [];

    public function __construct(
        private readonly S3Client $s3,
        private readonly PDO $db,
        private readonly string $bucket,
        private readonly array $allowedExtensions = self::DEFAULT_EXTENSIONS,
    ) {}

    public function scan(): int
    {
        echo "Scanning bucket '{$this->bucket}'...\n";

        $this->loadDirCache();

        $total = 0;
        $new = 0;
        $skipped = 0;
        $errors = 0;
        $filtered = 0;

        $params = ['Bucket' => $this->bucket];

        do {
            $result = $this->s3->listObjectsV2($params);
            $objects = $result['Contents'] ?? [];

            foreach ($objects as $object) {
                $key = $object['Key'];

                if (!$this->hasAllowedExtension($key)) {
                    $filtered++;
                    continue;
                }

                $total++;

                try {
                    if ($this->imageExists($key)) {
                        $skipped++;
                        echo "[{$total}] {$key} → exists, skipped\n";
                    } else {
                        $dirId = $this->ensureDirectoryHierarchy($key);
                        $this->insertImage($key, $object, $dirId);
                        $new++;
                        echo "[{$total}] {$key} → new\n";
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    echo "[{$total}] {$key} → ERROR: {$e->getMessage()}\n";
                }
            }

            $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
        } while ($result['IsTruncated'] ?? false);

        echo "\nDone. {$total} scanned, {$new} new, {$skipped} skipped, {$filtered} filtered, {$errors} errors.\n";

        return $errors > 0 ? 1 : 0;
    }

    private function hasAllowedExtension(string $key): bool
    {
        $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        return in_array($extension, $this->allowedExtensions, true);
    }

    private function imageExists(string $name): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM images WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        return $stmt->fetchColumn() !== false;
    }

    private function insertImage(string $key, array $object, int $dirId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO images (name, size, time, hash, dir_id) VALUES (:name, :size, :time, :hash, :dir_id)'
        );

        $lastModified = $object['LastModified'] ?? null;
        $timestamp = $lastModified instanceof \DateTimeInterface
            ? $lastModified->getTimestamp()
            : 0;

        $stmt->execute([
            'name' => $key,
            'size' => (int) ($object['Size'] ?? 0),
            'time' => $timestamp,
            'hash' => trim($object['ETag'] ?? '', '"'),
            'dir_id' => $dirId,
        ]);
    }

    private function ensureDirectoryHierarchy(string $key): int
    {
        $dirname = dirname($key);

        if ($dirname === '.' || $dirname === '/') {
            return 0;
        }

        if (isset($this->dirCache[$dirname])) {
            return $this->dirCache[$dirname];
        }

        $parts = explode('/', $dirname);
        $path = '';
        $parentId = 0;

        foreach ($parts as $part) {
            $path = $path === '' ? $part : "{$path}/{$part}";

            if (isset($this->dirCache[$path])) {
                $parentId = $this->dirCache[$path];
                continue;
            }

            $stmt = $this->db->prepare('SELECT id FROM dirs WHERE dirname = :dirname LIMIT 1');
            $stmt->execute(['dirname' => $path]);
            $row = $stmt->fetch();

            if ($row) {
                $id = (int) $row['id'];
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO dirs (dirname, bucket, parent_id) VALUES (:dirname, :bucket, :parent_id)'
                );
                $stmt->execute([
                    'dirname' => $path,
                    'bucket' => $this->bucket,
                    'parent_id' => $parentId,
                ]);
                $id = (int) $this->db->lastInsertId();
            }

            $this->dirCache[$path] = $id;
            $parentId = $id;
        }

        return $parentId;
    }

    private function loadDirCache(): void
    {
        $stmt = $this->db->query('SELECT id, dirname FROM dirs');
        while ($row = $stmt->fetch()) {
            $this->dirCache[$row['dirname']] = (int) $row['id'];
        }
    }
}
