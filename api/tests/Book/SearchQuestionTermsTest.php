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

    public function testDeathDateAndStaysOnOriginalTokens(): void
    {
        $question = 'Quelle est la date du décès du prophète Kadima ?';
        $tokens = SearchQuestionTerms::tokens($question);

        self::assertContains('date', $tokens);
        self::assertContains('décès', $tokens);
        self::assertNotContains('âme', $tokens);
        self::assertSame(
            SearchQuestionTerms::tsquery($question),
            implode(' & ', $tokens)
        );
    }

    public function testDeathDateOrExpandsSynonymsAsAndGroups(): void
    {
        $or = SearchQuestionTerms::tsqueryOr('Quelle est la date du décès du prophète Kadima ?');

        self::assertNotNull($or);
        self::assertStringContainsString('décès', $or);
        self::assertStringContainsString('mort', $or);
        self::assertStringContainsString('âme', $or);
        self::assertStringContainsString('révérence', $or);
        self::assertStringContainsString('&', $or);
        self::assertStringNotContainsString('date &', $or);
        self::assertTrue(
            str_contains($or, 'kadima') || str_contains($or, 'prophète'),
            'expanded query should keep prophet entity terms'
        );
    }
}
