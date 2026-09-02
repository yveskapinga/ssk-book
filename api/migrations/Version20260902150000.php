<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow several embedded figures per page, with vertical placement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book_page_images DROP CONSTRAINT IF EXISTS uq_book_page_images_page');
        $this->addSql('ALTER TABLE book_page_images ADD COLUMN IF NOT EXISTS sort_index INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE book_page_images ADD COLUMN IF NOT EXISTS y_ratio NUMERIC(6,4) NOT NULL DEFAULT 0.4000');
        $this->addSql('ALTER TABLE book_page_images DROP CONSTRAINT IF EXISTS uq_book_page_images_slot');
        $this->addSql('ALTER TABLE book_page_images ADD CONSTRAINT uq_book_page_images_slot UNIQUE (version_id, page_number, sort_index)');
        $this->addSql('ALTER TABLE book_page_images DROP CONSTRAINT IF EXISTS chk_book_page_images_ratio');
        $this->addSql('ALTER TABLE book_page_images ADD CONSTRAINT chk_book_page_images_ratio CHECK (y_ratio >= 0 AND y_ratio <= 1)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book_page_images DROP CONSTRAINT IF EXISTS chk_book_page_images_ratio');
        $this->addSql('ALTER TABLE book_page_images DROP CONSTRAINT IF EXISTS uq_book_page_images_slot');
        $this->addSql('ALTER TABLE book_page_images DROP COLUMN IF EXISTS y_ratio');
        $this->addSql('ALTER TABLE book_page_images DROP COLUMN IF EXISTS sort_index');
        $this->addSql('ALTER TABLE book_page_images ADD CONSTRAINT uq_book_page_images_page UNIQUE (version_id, page_number)');
    }
}
