<?php

namespace App\Tests\Book;

use App\Book\BookChunker;
use PHPUnit\Framework\TestCase;

final class BookChunkerTest extends TestCase
{
    public function testItKeepsPageReferencesAndSequentialPositions(): void
    {
        $chunks = (new BookChunker())->chunk([
            ['pageNumber' => 1, 'normalizedText' => 'Premier paragraphe.'],
            ['pageNumber' => 2, 'normalizedText' => 'Deuxième paragraphe.'],
        ]);

        self::assertCount(1, $chunks);
        self::assertSame(0, $chunks[0]['position']);
        self::assertSame(1, $chunks[0]['startPage']);
        self::assertSame(2, $chunks[0]['endPage']);
        self::assertStringContainsString('Premier paragraphe.', $chunks[0]['content']);
        self::assertStringContainsString('Deuxième paragraphe.', $chunks[0]['content']);
    }

    public function testItSplitsLargeContent(): void
    {
        $paragraph = str_repeat('contenu ', 350);
        $chunks = (new BookChunker())->chunk([
            ['pageNumber' => 1, 'normalizedText' => $paragraph."\n\n".$paragraph],
        ]);

        self::assertCount(2, $chunks);
        self::assertSame([0, 1], array_column($chunks, 'position'));
    }
}
