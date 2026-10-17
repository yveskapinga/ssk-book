<?php

namespace App\Book;

use App\Shared\DomainException;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class PassageIndexer
{
    public function __construct(
        private Connection $connection,
        private BookRepository $books,
        private PageImageRepository $images,
        private ReadingRepository $reading,
        private BookPassageSplitter $splitter,
    ) {
    }

    /**
     * @return array{passages:int,seeded:int}
     */
    public function rebuild(string $versionId, bool $seedProgress = false): array
    {
        $version = $this->books->findVersion($versionId);
        if (null === $version) {
            throw new DomainException('Version introuvable.', 404);
        }

        $pages = $this->books->pagesForVersion($versionId);
        $figures = $this->images->forVersion($versionId);
        $chunks = $this->books->chunksMetaForVersion($versionId);
        $nodes = $this->books->tableOfContents($versionId);
        $split = $this->splitter->split($pages, $figures, $chunks, $nodes);
        $progressRows = $seedProgress ? $this->reading->progressRowsForVersion($versionId) : [];

        $count = 0;
        $seeded = 0;
        $this->connection->transactional(function () use ($versionId, $split, $progressRows, $seedProgress, &$count, &$seeded): void {
            $rows = array_map(static fn (array $passage): array => [
                'id' => Uuid::v7()->toRfc4122(),
                'version_id' => $versionId,
                'position' => $passage['position'],
                'page_number' => $passage['pageNumber'],
                'node_id' => $passage['nodeId'],
                'chunk_id' => $passage['chunkId'],
                'figure_id' => $passage['figureId'],
                'kind' => $passage['kind'],
                'body' => $passage['body'],
            ], $split);
            $this->books->replacePassages($versionId, $rows);
            $count = count($rows);
            if ($seedProgress) {
                $seeded = $this->seedFromPageProgress($versionId, $progressRows);
            }
        });

        return ['passages' => $count, 'seeded' => $seeded];
    }

    /**
     * @param list<array{user_id:string,chunk_id:string,page_number:int|string,passage_id:?string}> $progressRows
     */
    private function seedFromPageProgress(string $versionId, array $progressRows): int
    {
        $seeded = 0;
        foreach ($progressRows as $row) {
            $pageNumber = (int) $row['page_number'];
            $target = $this->books->firstPassageAtPage($versionId, $pageNumber) ?? $this->books->firstPassage($versionId);
            if (null === $target) {
                continue;
            }
            $before = $this->books->passagesBeforePosition($versionId, (int) $target['position']);
            $this->reading->markPassagesRead((string) $row['user_id'], array_column($before, 'id'));
            $chunkId = (string) ($target['chunk_id'] ?? $row['chunk_id']);
            if ('' === $chunkId) {
                $chunkId = (string) $row['chunk_id'];
            }
            $this->reading->upsertProgress(
                (string) $row['user_id'],
                $versionId,
                $chunkId,
                (int) $target['page_number'],
                (string) $target['id'],
            );
            ++$seeded;
        }

        return $seeded;
    }
}
