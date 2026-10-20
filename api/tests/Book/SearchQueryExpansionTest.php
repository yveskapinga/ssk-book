<?php

namespace App\Tests\Book;

use App\Book\SearchQueryExpansion;
use PHPUnit\Framework\TestCase;

final class SearchQueryExpansionTest extends TestCase
{
    public function testExpandsDeathSynonyms(): void
    {
        $expanded = SearchQueryExpansion::expand(['décès', 'date']);

        self::assertContains('décès', $expanded);
        self::assertContains('date', $expanded);
        self::assertContains('mort', $expanded);
        self::assertTrue(
            in_array('âme', $expanded, true) || in_array('ame', $expanded, true),
            'expected âme/ame in expansion'
        );
        self::assertTrue(
            in_array('révérence', $expanded, true) || in_array('reverence', $expanded, true),
            'expected révérence/reverence in expansion'
        );
    }

    public function testLeavesUnknownTokensUnchanged(): void
    {
        self::assertSame(['vendredi', 'culte'], SearchQueryExpansion::expand(['vendredi', 'culte']));
    }

    public function testExpandsFromShortAmeInQuestionBody(): void
    {
        $expanded = SearchQueryExpansion::expand(
            ['rendu', 'date', 'kadima'],
            "Le prophète kadima a rendu l'âme à quelle date?"
        );

        self::assertContains('mort', $expanded);
        self::assertContains('âme', $expanded);
    }

    public function testTsqueryExpandedAndGroupsForDeath(): void
    {
        $q = SearchQueryExpansion::tsqueryExpanded(
            ['date', 'décès', 'prophète', 'kadima'],
            'Quelle est la date du décès du prophète Kadima ?'
        );

        self::assertNotNull($q);
        self::assertStringContainsString('âme', $q);
        self::assertStringContainsString('&', $q);
        self::assertMatchesRegularExpression('/\(.*décès.*\)/', $q);
    }
}
