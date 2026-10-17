<?php

namespace App\Book;

use App\Audit\AuditRepository;
use App\Identity\User;
use App\Shared\DomainException;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class PageImageService
{
    public function __construct(
        private Connection $connection,
        private BookRepository $books,
        private PageImageRepository $images,
        private PdfEmbeddedImageExtractor $extractor,
        private AuditRepository $audit,
    ) {
    }

    public function render(User $actor, string $versionId): array
    {
        $version = $this->books->findVersion($versionId);
        if (null === $version) {
            throw new DomainException('Version introuvable.', 404);
        }
        if (!is_file((string) $version['source_path'])) {
            throw new DomainException('Le PDF source de cette version est introuvable.', 404);
        }

        $versionDir = dirname((string) $version['source_path']);
        $directory = $versionDir.'/figures';
        $figures = $this->extractor->extract((string) $version['source_path'], $directory);
        foreach (glob($versionDir.'/pages/page-*.jpg') ?: [] as $raster) {
            unlink($raster);
        }
        $rows = array_map(static fn (array $figure): array => [
            'id' => Uuid::v7()->toRfc4122(),
            'version_id' => $versionId,
            'page_number' => $figure['pageNumber'],
            'sort_index' => $figure['sortIndex'],
            'y_ratio' => number_format($figure['yRatio'], 4, '.', ''),
            'storage_path' => $figure['path'],
            'mime_type' => $figure['mime'],
            'width_px' => $figure['width'],
            'height_px' => $figure['height'],
            'byte_size' => $figure['byteSize'],
        ], $figures);

        $this->connection->transactional(function () use ($actor, $versionId, $rows): void {
            $this->images->replace($versionId, $rows);
            $this->audit->append($actor->id, 'BOOK_VERSION_FIGURES_EXTRACTED', 'BOOK_VERSION', $versionId, ['images' => count($rows)]);
        });

        return ['images' => count($rows), 'pages' => count($rows)];
    }
}
