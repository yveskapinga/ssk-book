<?php

namespace App\Tests\Book;

use App\Book\BookPassageSplitter;
use PHPUnit\Framework\TestCase;

final class BookPassageSplitterTest extends TestCase
{
    public function testItMergesShortFragmentsWithTheNextParagraph(): void
    {
        $passages = (new BookPassageSplitter())->split([
            ['pageNumber' => 1, 'normalizedText' => "Chapitre 1\n\n".str_repeat('Le récit continue. ', 12)],
        ]);

        self::assertCount(1, $passages);
        self::assertSame('TEXT', $passages[0]['kind']);
        self::assertStringContainsString('Chapitre 1', $passages[0]['body']);
        self::assertSame(0, $passages[0]['position']);
    }

    public function testItInsertsImagesAmongParagraphs(): void
    {
        $longA = str_repeat('Premier paragraphe du livre. ', 8);
        $longB = str_repeat('Deuxième paragraphe du livre. ', 8);
        $passages = (new BookPassageSplitter())->split(
            [['pageNumber' => 3, 'normalizedText' => $longA."\n\n".$longB]],
            [['id' => 'fig-1', 'page_number' => 3, 'y_ratio' => 0.5, 'sort_index' => 0]],
            [['id' => 'chunk-1', 'start_page' => 1, 'end_page' => 10]],
            [['id' => 'node-1', 'start_page' => 1, 'end_page' => 10, 'node_type' => 'CHAPTER']],
        );

        self::assertSame(['TEXT', 'IMAGE', 'TEXT'], array_column($passages, 'kind'));
        self::assertSame('fig-1', $passages[1]['figureId']);
        self::assertSame('chunk-1', $passages[0]['chunkId']);
        self::assertSame('node-1', $passages[0]['nodeId']);
        self::assertSame(3, $passages[1]['pageNumber']);
    }

    public function testItKeepsImageOnlyPages(): void
    {
        $passages = (new BookPassageSplitter())->split(
            [['pageNumber' => 8, 'normalizedText' => '   ']],
            [['id' => 'fig-8', 'page_number' => 8, 'y_ratio' => 0.2, 'sort_index' => 0]],
        );

        self::assertCount(1, $passages);
        self::assertSame('IMAGE', $passages[0]['kind']);
        self::assertSame('fig-8', $passages[0]['figureId']);
    }

    public function testItSkipsEmptyPagesWithoutFigures(): void
    {
        $passages = (new BookPassageSplitter())->split([
            ['pageNumber' => 2, 'normalizedText' => ""],
        ]);

        self::assertSame([], $passages);
    }
}
