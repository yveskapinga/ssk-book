<?php

namespace App\Book;

final class ReadingConfidence
{
    public function __construct(
        private readonly int $minDisplayedMs = 5000,
        private readonly int $msPerCharacter = 80,
        private readonly float $threshold = 0.7,
    ) {
    }

    public function score(int $displayedMs, int $bodyLength, bool $sequential): float
    {
        $needed = max($this->minDisplayedMs, $bodyLength * $this->msPerCharacter);
        $timeScore = min(1.0, $displayedMs / max(1, $needed));
        $seqBonus = $sequential ? 0.15 : 0.0;

        return min(1.0, round($timeScore * 0.85 + $seqBonus, 2));
    }

    public function shouldMarkRead(float $confidence): bool
    {
        return $confidence >= $this->threshold;
    }

    public function estimatedSeconds(int $bodyLength): int
    {
        $needed = max($this->minDisplayedMs, $bodyLength * $this->msPerCharacter);

        return max(4, (int) ceil(($needed * 0.65) / 1000));
    }
}
