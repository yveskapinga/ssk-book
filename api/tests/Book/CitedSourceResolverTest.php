<?php

namespace App\Tests\Book;

use App\Book\CitedSourceResolver;
use PHPUnit\Framework\TestCase;

final class CitedSourceResolverTest extends TestCase
{
    public function testKeepsExactRetrievedIds(): void
    {
        $retrieved = [
            ['id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'start_page' => 39, 'end_page' => 40],
            ['id' => '11111111-2222-3333-4444-555555555555', 'start_page' => 195, 'end_page' => 201],
        ];

        $ids = CitedSourceResolver::resolve(
            ['aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'],
            $retrieved,
            false,
        );

        self::assertSame(['aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'], $ids);
    }

    public function testMapsPageCitationsToRetrievedChunks(): void
    {
        $retrieved = [
            ['id' => 'src-luse', 'start_page' => 195, 'end_page' => 201],
            ['id' => 'src-other', 'start_page' => 10, 'end_page' => 12],
        ];

        $ids = CitedSourceResolver::resolve(['page 200', '201'], $retrieved, false);

        self::assertSame(['src-luse'], $ids);
    }

    public function testFallsBackToRetrievedSourcesWhenModelSkipsIds(): void
    {
        $retrieved = [
            ['id' => 'src-1', 'start_page' => 39, 'end_page' => 40],
            ['id' => 'src-2', 'start_page' => 195, 'end_page' => 201],
        ];

        $ids = CitedSourceResolver::resolve(['SOURCE 1'], $retrieved, false);

        self::assertSame(['src-1', 'src-2'], $ids);
    }

    public function testDoesNotInventSourcesWhenEvidenceIsInsufficient(): void
    {
        $ids = CitedSourceResolver::resolve([], [['id' => 'src-1', 'start_page' => 1, 'end_page' => 1]], true);

        self::assertSame([], $ids);
    }
}
