<?php

namespace App\Tests\Book;

use App\Book\ReadingConfidence;
use App\Book\ReadingProgressPolicy;
use PHPUnit\Framework\TestCase;

final class ReadingProgressPolicyTest extends TestCase
{
    private ReadingProgressPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ReadingProgressPolicy(new ReadingConfidence());
    }

    public function testSequentialDwellMarksRead(): void
    {
        $decision = $this->policy->decide(false, true, 5000, 0);

        self::assertSame('READ', $decision['status']);
        self::assertGreaterThanOrEqual(0.7, $decision['confidence']);
    }

    public function testNonSequentialNeverMarksRead(): void
    {
        $decision = $this->policy->decide(false, false, 120_000, 20);

        self::assertSame('IN_PROGRESS', $decision['status']);
    }

    public function testShortDwellStaysInProgress(): void
    {
        $decision = $this->policy->decide(false, true, 1000, 200);

        self::assertSame('IN_PROGRESS', $decision['status']);
    }

    public function testAlreadyReadStaysRead(): void
    {
        $decision = $this->policy->decide(true, false, 0, 80);

        self::assertSame('READ', $decision['status']);
    }

    public function testExplicitAdvanceMarksReadWithoutLongDwell(): void
    {
        $decision = $this->policy->decide(false, true, 400, 400, true);

        self::assertSame('READ', $decision['status']);
        self::assertGreaterThanOrEqual(0.7, $decision['confidence']);
    }

    public function testImageEstimateUsesMinimumDuration(): void
    {
        self::assertSame(4, $this->policy->estimatedSeconds(0));
    }
}
