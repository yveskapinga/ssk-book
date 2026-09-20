<?php

namespace App\Book;

final class BookPassageSplitter
{
    public const MIN_CHARS = 80;

    /**
     * @param list<array{pageNumber:int,normalizedText:string}> $pages
     * @param list<array{id:string,page_number:int,y_ratio:float|int|string,sort_index?:int}> $figures
     * @param list<array{id:string,start_page:int,end_page:int}> $chunks
     * @param list<array{id:string,start_page:?int,end_page:?int,node_type?:string}> $nodes
     * @return list<array{position:int,pageNumber:int,kind:string,body:string,figureId:?string,chunkId:?string,nodeId:?string}>
     */
    public function split(array $pages, array $figures = [], array $chunks = [], array $nodes = []): array
    {
        $byPage = [];
        foreach ($figures as $figure) {
            $byPage[(int) $figure['page_number']][] = $figure;
        }

        $passages = [];
        foreach ($pages as $page) {
            $pageNumber = (int) $page['pageNumber'];
            $paragraphs = $this->mergedParagraphs((string) $page['normalizedText']);
            $pageFigures = $byPage[$pageNumber] ?? [];
            usort($pageFigures, static function (array $left, array $right): int {
                $ratio = ((float) $left['y_ratio']) <=> ((float) $right['y_ratio']);

                return 0 !== $ratio ? $ratio : ((int) ($left['sort_index'] ?? 0) <=> (int) ($right['sort_index'] ?? 0));
            });

            $count = count($paragraphs);
            $figureAt = [];
            foreach ($pageFigures as $figure) {
                $insertAt = 0 === $count ? 0 : (int) max(0, min($count, (int) round(((float) $figure['y_ratio']) * $count)));
                $figureAt[$insertAt][] = $figure;
            }

            for ($index = 0; $index <= $count; ++$index) {
                foreach ($figureAt[$index] ?? [] as $figure) {
                    $passages[] = $this->makePassage($passages, $pageNumber, 'IMAGE', '', (string) $figure['id'], $chunks, $nodes);
                }
                if ($index < $count) {
                    $passages[] = $this->makePassage($passages, $pageNumber, 'TEXT', $paragraphs[$index], null, $chunks, $nodes);
                }
            }
        }

        return $passages;
    }

    /**
     * @return list<string>
     */
    private function mergedParagraphs(string $text): array
    {
        $parts = preg_split('/\n\s*\n/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $merged = [];
        $buffer = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ('' === $part) {
                continue;
            }
            if ('' === $buffer) {
                $buffer = $part;
                continue;
            }
            if (mb_strlen($buffer) < self::MIN_CHARS) {
                $buffer .= "\n\n".$part;
                continue;
            }
            $merged[] = $buffer;
            $buffer = $part;
        }
        if ('' !== $buffer) {
            if ($merged !== [] && mb_strlen($buffer) < self::MIN_CHARS) {
                $merged[array_key_last($merged)] .= "\n\n".$buffer;
            } else {
                $merged[] = $buffer;
            }
        }

        return $merged;
    }

    /**
     * @param list<array{position:int,pageNumber:int,kind:string,body:string,figureId:?string,chunkId:?string,nodeId:?string}> $passages
     * @param list<array{id:string,start_page:int,end_page:int}> $chunks
     * @param list<array{id:string,start_page:?int,end_page:?int,node_type?:string}> $nodes
     * @return array{position:int,pageNumber:int,kind:string,body:string,figureId:?string,chunkId:?string,nodeId:?string}
     */
    private function makePassage(array $passages, int $pageNumber, string $kind, string $body, ?string $figureId, array $chunks, array $nodes): array
    {
        return [
            'position' => count($passages),
            'pageNumber' => $pageNumber,
            'kind' => $kind,
            'body' => $body,
            'figureId' => $figureId,
            'chunkId' => $this->coveringChunkId($chunks, $pageNumber),
            'nodeId' => $this->coveringNodeId($nodes, $pageNumber),
        ];
    }

    /**
     * @param list<array{id:string,start_page:int,end_page:int}> $chunks
     */
    private function coveringChunkId(array $chunks, int $pageNumber): ?string
    {
        $fallback = null;
        foreach ($chunks as $chunk) {
            $start = (int) $chunk['start_page'];
            $end = (int) $chunk['end_page'];
            if ($start <= $pageNumber && $end >= $pageNumber) {
                return (string) $chunk['id'];
            }
            if ($start <= $pageNumber) {
                $fallback = (string) $chunk['id'];
            }
        }

        return $fallback ?? (isset($chunks[0]) ? (string) $chunks[0]['id'] : null);
    }

    /**
     * @param list<array{id:string,start_page:?int,end_page:?int,node_type?:string}> $nodes
     */
    private function coveringNodeId(array $nodes, int $pageNumber): ?string
    {
        $best = null;
        $bestScore = null;
        foreach ($nodes as $node) {
            $start = (int) ($node['start_page'] ?? 0);
            $end = (int) ($node['end_page'] ?? $start);
            if ($start < 1 || $pageNumber < $start || $pageNumber > $end) {
                continue;
            }
            $type = (string) ($node['node_type'] ?? '');
            $span = max(0, $end - $start);
            $rank = match ($type) {
                'CHAPTER' => 0,
                'SECTION' => 1,
                default => 2,
            };
            $score = [$rank, $span];
            if (null === $bestScore || $score < $bestScore) {
                $best = (string) $node['id'];
                $bestScore = $score;
            }
        }

        return $best;
    }
}
