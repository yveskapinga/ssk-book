<?php

namespace App\Tests\Book;

use App\Book\EmbeddingService;
use PHPUnit\Framework\TestCase;

final class EmbeddingTextTest extends TestCase
{
    public function testPrefixIncludesTitleAndPages(): void
    {
        $text = EmbeddingService::embedText([
            'book_title' => 'Le Prophete KADIMA Bakenge',
            'start_page' => 38,
            'end_page' => 39,
            'content' => 'Corps du passage.',
        ]);

        self::assertStringStartsWith("Livre: Le Prophete KADIMA Bakenge\nPages: 38-39\n", $text);
        self::assertStringEndsWith('Corps du passage.', $text);
    }
}
