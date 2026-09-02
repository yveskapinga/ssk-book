<?php

namespace App\Book;

final class BookStructureDetector
{
    /**
     * @param list<array{pageNumber:int,normalizedText:string}> $pages
     * @return list<array{nodeType:string,title:string,position:int,startPage:int,endPage:int,parentIndex:?int}>
     */
    public function detect(array $pages): array
    {
        $candidates = [];
        foreach ($pages as $page) {
            $lines = preg_split('/\n+/u', $page['normalizedText']) ?: [];
            foreach ($lines as $line) {
                $title = trim($line);
                $type = $this->classify($title);
                if (null === $type) {
                    continue;
                }
                $candidates[] = [
                    'nodeType' => $type,
                    'title' => $this->normalizeTitle($title),
                    'startPage' => $page['pageNumber'],
                ];
            }
        }

        $nodes = [];
        $lastPart = null;
        foreach ($candidates as $index => $candidate) {
            $endPage = $candidates[$index + 1]['startPage'] ?? ($pages === [] ? $candidate['startPage'] : (int) $pages[array_key_last($pages)]['pageNumber']);
            if ($endPage < $candidate['startPage']) {
                $endPage = $candidate['startPage'];
            }
            $parentIndex = null;
            if ('PART' === $candidate['nodeType']) {
                $lastPart = count($nodes);
            } elseif ('CHAPTER' === $candidate['nodeType'] && null !== $lastPart) {
                $parentIndex = $lastPart;
            }
            $nodes[] = [
                'nodeType' => $candidate['nodeType'],
                'title' => $candidate['title'],
                'position' => count($nodes),
                'startPage' => $candidate['startPage'],
                'endPage' => $endPage,
                'parentIndex' => $parentIndex,
            ];
        }

        return $nodes;
    }

    private function classify(string $title): ?string
    {
        if (mb_strlen($title) < 4 || mb_strlen($title) > 120) {
            return null;
        }
        if (preg_match('/^(premi[eè]re|deuxi[eè]me|troisi[eè]me|quatri[eè]me|cinqui[eè]me)\s+partie\b/iu', $title)
            || preg_match('/^partie\s+([ivxlcdm]+|\d+)\b/iu', $title)) {
            return 'PART';
        }
        if (preg_match('/^(chapitre|chapter)\s+\d+/iu', $title)
            || preg_match('/^(pr[eé]ambule|introduction|conclusion|epilogue|épilogue)\b/iu', $title)) {
            return 'CHAPTER';
        }
        if (preg_match('/^section\s+\d+/iu', $title)) {
            return 'SECTION';
        }

        return null;
    }

    private function normalizeTitle(string $title): string
    {
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return mb_substr($title, 0, 255);
    }
}
