<?php

namespace App\Tests\Book;

use App\Book\BookStructureDetector;
use PHPUnit\Framework\TestCase;

final class BookStructureDetectorTest extends TestCase
{
    public function testItDetectsPartsAndChaptersWithPageRanges(): void
    {
        $nodes = (new BookStructureDetector())->detect([
            ['pageNumber' => 12, 'normalizedText' => "Préambule\nLe texte d'ouverture."],
            ['pageNumber' => 19, 'normalizedText' => "Première partie\nDébut du récit."],
            ['pageNumber' => 45, 'normalizedText' => "Chapitre 1\nLa jeunesse."],
            ['pageNumber' => 104, 'normalizedText' => "Deuxième partie\nLa suite."],
        ]);

        self::assertSame(['CHAPTER', 'PART', 'CHAPTER', 'PART'], array_column($nodes, 'nodeType'));
        self::assertSame(12, $nodes[0]['startPage']);
        self::assertSame(19, $nodes[1]['startPage']);
        self::assertNotNull($nodes[2]['parentIndex']);
        self::assertSame('PART', $nodes[$nodes[2]['parentIndex']]['nodeType']);
    }

    public function testItIgnoresOrdinaryParagraphs(): void
    {
        $nodes = (new BookStructureDetector())->detect([
            ['pageNumber' => 1, 'normalizedText' => "Le Prophète marchait vers le village le matin."],
        ]);

        self::assertSame([], $nodes);
    }
}
