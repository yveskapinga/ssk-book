<?php

namespace App\Book;

final class BookChunker
{
    private const TARGET_CHARACTERS = 900;
    private const OVERLAP_CHARACTERS = 150;
    private const MAX_PAGE_SPAN = 2;

    /**
     * @param list<array{pageNumber:int,normalizedText:string}> $pages
     * @return list<array{position:int,startPage:int,endPage:int,content:string,tokenEstimate:int}>
     */
    public function chunk(array $pages): array
    {
        $chunks = [];
        $buffer = '';
        $startPage = 1;
        $endPage = 1;

        foreach ($pages as $page) {
            $paragraphs = preg_split('/\n\s*\n/u', $page['normalizedText'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if ('' === $paragraph) {
                    continue;
                }
                if ('' === $buffer) {
                    $startPage = $page['pageNumber'];
                }

                $candidate = '' === $buffer ? $paragraph : $buffer."\n\n".$paragraph;
                $pageSpanExceeded = '' !== $buffer
                    && ((int) $page['pageNumber'] - $startPage) >= self::MAX_PAGE_SPAN;
                $sizeExceeded = '' !== $buffer && mb_strlen($candidate) > self::TARGET_CHARACTERS;

                if ($sizeExceeded || $pageSpanExceeded) {
                    $chunks[] = $this->makeChunk($buffer, $startPage, $endPage, count($chunks));
                    $buffer = $this->overlapTail($buffer);
                    if ('' === $buffer) {
                        $startPage = $page['pageNumber'];
                    } else {
                        // Overlap stays on previous page range end; new text continues current page.
                        $startPage = $endPage;
                    }
                    $candidate = '' === $buffer ? $paragraph : $buffer."\n\n".$paragraph;
                }

                $buffer = $candidate;
                $endPage = $page['pageNumber'];
            }
        }
        if ('' !== $buffer) {
            $chunks[] = $this->makeChunk($buffer, $startPage, $endPage, count($chunks));
        }

        return $chunks;
    }

    private function overlapTail(string $content): string
    {
        $content = trim($content);
        if ($content === '' || self::OVERLAP_CHARACTERS <= 0) {
            return '';
        }
        if (mb_strlen($content) <= self::OVERLAP_CHARACTERS) {
            return $content;
        }

        $tail = mb_substr($content, -self::OVERLAP_CHARACTERS);
        // Prefer starting at a paragraph boundary inside the tail window.
        if (preg_match('/\n\s*\n(.+)$/us', $tail, $m) === 1) {
            return trim($m[1]);
        }

        return ltrim($tail);
    }

    private function makeChunk(string $content, int $startPage, int $endPage, int $position): array
    {
        return [
            'position' => $position,
            'startPage' => $startPage,
            'endPage' => $endPage,
            'content' => trim($content),
            'tokenEstimate' => max(1, (int) ceil(mb_strlen($content) / 4)),
        ];
    }
}
