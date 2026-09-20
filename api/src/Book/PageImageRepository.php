<?php

namespace App\Book;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class PageImageRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function replace(string $versionId, array $images): void
    {
        $this->connection->executeStatement('DELETE FROM book_page_images WHERE version_id = :version_id', ['version_id' => $versionId]);
        foreach ($images as $image) {
            $this->connection->insert('book_page_images', $image);
        }
    }

    public function forPage(string $versionId, int $pageNumber): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, page_number, sort_index, y_ratio, width_px, height_px FROM book_page_images WHERE version_id = :id AND page_number = :page ORDER BY y_ratio, sort_index',
            ['id' => $versionId, 'page' => $pageNumber],
            ['page' => ParameterType::INTEGER],
        );
    }

    public function byId(string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, version_id, storage_path, mime_type FROM book_page_images WHERE id = :id',
            ['id' => $id],
        );

        return false === $row ? null : $row;
    }

    public function countForVersion(string $versionId): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM book_page_images WHERE version_id = :id', ['id' => $versionId]);
    }

    public function forVersion(string $versionId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, page_number, sort_index, y_ratio, width_px, height_px FROM book_page_images WHERE version_id = :id ORDER BY page_number, y_ratio, sort_index',
            ['id' => $versionId],
        );
    }
}
