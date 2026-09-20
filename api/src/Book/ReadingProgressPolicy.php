<?php

namespace App\Book;

final class ReadingProgressPolicy
{
    public function __construct(private readonly ReadingConfidence $confidence)
    {
    }

    /**
     * @return array{status:string,confidence:float}
     */
    public function decide(bool $alreadyRead, bool $sequential, int $displayedMs, int $bodyLength, bool $explicitAdvance = false): array
    {
        $score = $this->confidence->score($displayedMs, $bodyLength, $sequential);
        if ($alreadyRead || $explicitAdvance) {
            return ['status' => 'READ', 'confidence' => max($score, 0.7)];
        }
        if ($sequential && $this->confidence->shouldMarkRead($score)) {
            return ['status' => 'READ', 'confidence' => $score];
        }

        return ['status' => 'IN_PROGRESS', 'confidence' => $score];
    }

    public function estimatedSeconds(int $bodyLength): int
    {
        return $this->confidence->estimatedSeconds($bodyLength);
    }
}
