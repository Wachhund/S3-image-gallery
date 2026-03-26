<?php

declare(strict_types=1);

namespace S3Gallery\Service;

use PDO;

final class GalleryService
{
    public function __construct(
        private readonly PDO $db,
    ) {}

    public function getTopLevelDirs(): array
    {
        $stmt = $this->db->query(
            'SELECT d.id, d.dirname, COUNT(i.id) AS image_count
             FROM dirs d
             LEFT JOIN dirs cd ON (cd.parent_id = d.id OR cd.id = d.id)
             LEFT JOIN images i ON i.dir_id = cd.id
             WHERE d.parent_id = 0
             GROUP BY d.id, d.dirname
             ORDER BY d.dirname ASC'
        );

        return $stmt->fetchAll();
    }

    public function getSubDirs(int $parentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.id, d.dirname, COUNT(i.id) AS image_count
             FROM dirs d
             LEFT JOIN images i ON i.dir_id = d.id
             WHERE d.parent_id = :parent_id
             GROUP BY d.id, d.dirname
             ORDER BY d.dirname ASC'
        );
        $stmt->execute(['parent_id' => $parentId]);

        return $stmt->fetchAll();
    }

    public function getAllDirs(): array
    {
        $stmt = $this->db->query('SELECT id, dirname FROM dirs ORDER BY dirname ASC');
        return $stmt->fetchAll();
    }

    public function getDir(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM dirs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function getImagesWithThumbs(int $dirId, int $page = 1, int $perPage = 60): array
    {
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            'SELECT i.id, i.name, i.size, t.name AS thumb_name
             FROM images i
             LEFT JOIN thumbs t ON i.id = t.image_id
             WHERE i.dir_id = :dir_id
             ORDER BY i.name ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('dir_id', $dirId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getImageCount(int $dirId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM images WHERE dir_id = :dir_id');
        $stmt->execute(['dir_id' => $dirId]);

        return (int) $stmt->fetchColumn();
    }

    public function getImage(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT i.id, i.name, t.name AS thumb_name
             FROM images i
             LEFT JOIN thumbs t ON i.id = t.image_id
             WHERE i.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createEventGallery(string $date, string $eventName, string $bucket): int
    {
        $year = substr($date, 0, 4);
        $dirname = "{$date} {$eventName}";
        $fullPath = "{$year}/{$dirname}";

        $yearId = $this->ensureYearDir($year, $bucket);

        $stmt = $this->db->prepare(
            'INSERT INTO dirs (dirname, bucket, parent_id) VALUES (:dirname, :bucket, :parent_id)'
        );
        $stmt->execute([
            'dirname' => $fullPath,
            'bucket' => $bucket,
            'parent_id' => $yearId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function eventGalleryExists(string $date, string $eventName): bool
    {
        $year = substr($date, 0, 4);
        $fullPath = "{$year}/{$date} {$eventName}";

        $stmt = $this->db->prepare('SELECT 1 FROM dirs WHERE dirname = :dirname LIMIT 1');
        $stmt->execute(['dirname' => $fullPath]);
        return $stmt->fetchColumn() !== false;
    }

    public static function validateEventName(string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return 'Eventname darf nicht leer sein.';
        }
        if (mb_strlen($name) > 100) {
            return 'Eventname darf maximal 100 Zeichen lang sein.';
        }
        if (preg_match('#[/\\\\]|\.\.#', $name)) {
            return 'Eventname darf keine Schrägstriche oder ".." enthalten.';
        }
        if (!preg_match('/^[\p{L}\p{N} \-_.]+$/u', $name)) {
            return 'Eventname darf nur Buchstaben, Zahlen, Leerzeichen, Bindestriche, Punkte und Unterstriche enthalten.';
        }
        return null;
    }

    public static function validateDate(string $date): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return 'Datum muss im Format YYYY-MM-DD sein.';
        }
        $parts = explode('-', $date);
        if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return 'Ungültiges Datum.';
        }
        return null;
    }

    private function ensureYearDir(string $year, string $bucket): int
    {
        $stmt = $this->db->prepare('SELECT id FROM dirs WHERE dirname = :dirname LIMIT 1');
        $stmt->execute(['dirname' => $year]);
        $row = $stmt->fetch();

        if ($row) {
            return (int) $row['id'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO dirs (dirname, bucket, parent_id) VALUES (:dirname, :bucket, 0)'
        );
        $stmt->execute(['dirname' => $year, 'bucket' => $bucket]);

        return (int) $this->db->lastInsertId();
    }

    public function buildBreadcrumbs(int $dirId): array
    {
        $crumbs = [];
        $currentId = $dirId;

        while ($currentId > 0) {
            $dir = $this->getDir($currentId);
            if ($dir === null) {
                break;
            }

            $label = basename($dir['dirname']);
            array_unshift($crumbs, [
                'label' => $label,
                'url' => '/browse/' . $dir['id'],
            ]);

            $currentId = (int) $dir['parent_id'];
        }

        array_unshift($crumbs, ['label' => 'Home', 'url' => '/']);

        if (!empty($crumbs)) {
            $crumbs[array_key_last($crumbs)]['url'] = null;
        }

        return $crumbs;
    }
}
