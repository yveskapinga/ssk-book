<?php

namespace App\System;

final readonly class HealthService
{
    public function __construct(private SystemRepository $repository)
    {
    }

    public function status(): array
    {
        $databaseAvailable = false;

        try {
            $databaseAvailable = $this->repository->databaseIsAvailable();
        } catch (\Throwable) {
        }

        return [
            'service' => 'ssk-book-api',
            'status' => $databaseAvailable ? 'ok' : 'degraded',
            'database' => $databaseAvailable ? 'up' : 'down',
            'httpStatus' => $databaseAvailable ? 200 : 503,
        ];
    }
}
