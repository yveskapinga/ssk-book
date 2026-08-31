<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable vector and trigram PostgreSQL extensions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Extensions are shared database capabilities and are not removed automatically.');
    }
}
