<?php

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class SqlFirstBoundariesTest extends TestCase
{
    public function testControllersAndServicesContainNoSql(): void
    {
        $script = dirname(__DIR__, 3).'/scripts/check-sql-first-boundaries.sh';
        $repository = dirname(__DIR__, 3);
        $output = [];
        $code = 0;
        exec(sprintf('cd %s && sh %s 2>&1', escapeshellarg($repository), escapeshellarg($script)), $output, $code);

        self::assertSame(0, $code, implode("\n", $output));
    }
}
