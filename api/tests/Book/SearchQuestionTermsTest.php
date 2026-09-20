<?php

namespace App\Tests\Book;

use App\Book\SearchQuestionTerms;
use PHPUnit\Framework\TestCase;

final class SearchQuestionTermsTest extends TestCase
{
    public function testKeepsGivenNameDespiteFrenchLuxuryWord(): void
    {
        $tokens = SearchQuestionTerms::tokens('est ce que le livre mentionne une fille Luxe du Prophète KADIMA?');

        self::assertContains('luxe', $tokens);
        self::assertContains('fille', $tokens);
        self::assertNotContains('kadima', $tokens);
        self::assertNotContains('livre', $tokens);
        self::assertNotContains('mentionne', $tokens);
    }

    public function testDateQuestionKeepsEventTermsAndDropsInterrogativePadding(): void
    {
        $question = 'Quand a eu lieu le premier culte inaugurale de vendredi?';
        $tokens = SearchQuestionTerms::tokens($question);

        self::assertContains('premier', $tokens);
        self::assertContains('culte', $tokens);
        self::assertContains('inaugurale', $tokens);
        self::assertContains('vendredi', $tokens);
        self::assertNotContains('quand', $tokens);
        self::assertNotContains('lieu', $tokens);
        self::assertSame('premier & culte & inaugurale & vendredi', SearchQuestionTerms::tsquery($question));
    }

    public function testKeepsLuseWhenSpelledCorrectly(): void
    {
        self::assertContains('luse', SearchQuestionTerms::tokens('une fille nommée Luse Kadima Luse'));
    }
}
