<?php

namespace App\Book;

final class BookChunker
{
    private const TARGET_CHARACTERS = 2400;

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
                if ('' !== $buffer && mb_strlen($buffer."\n\n".$paragraph) > self::TARGET_CHARACTERS) {
                    $chunks[] = $this->makeChunk($buffer, $startPage, $endPage, count($chunks));
                    $buffer = '';
                    $startPage = $page['pageNumber'];
                }
                $buffer .= ('' === $buffer ? '' : "\n\n").$paragraph;
                $endPage = $page['pageNumber'];
            }
        }
        if ('' !== $buffer) {
            $chunks[] = $this->makeChunk($buffer, $startPage, $endPage, count($chunks));
        }

        return $chunks;
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
