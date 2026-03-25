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
            'SELECT d.id, d.dirname,
                    (SELECT COUNT(*) FROM images i WHERE i.dir_id = d.id) AS image_count
             FROM dirs d
             WHERE d.parent_id = 0
             ORDER BY d.dirname ASC'
        );

        return $stmt->fetchAll();
    }

    public function getSubDirs(int $parentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.id, d.dirname,
                    (SELECT COUNT(*) FROM images i WHERE i.dir_id = d.id) AS image_count
             FROM dirs d
             WHERE d.parent_id = :parent_id
             ORDER BY d.dirname ASC'
        );
        $stmt->execute(['parent_id' => $parentId]);

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
