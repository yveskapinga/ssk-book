<?php

namespace App\System;

use Doctrine\DBAL\Connection;

final readonly class SystemRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function databaseIsAvailable(): bool
    {
        return 1 === (int) $this->connection->fetchOne('SELECT 1');
    }
}
