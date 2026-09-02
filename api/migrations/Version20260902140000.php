<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Page images extracted from the source PDF for the reader';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE book_page_images (
    id UUID PRIMARY KEY,
    version_id UUID NOT NULL REFERENCES book_versions(id) ON DELETE CASCADE,
    page_number INTEGER NOT NULL,
    storage_path TEXT NOT NULL,
    mime_type VARCHAR(80) NOT NULL,
    width_px INTEGER NOT NULL,
    height_px INTEGER NOT NULL,
    byte_size INTEGER NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_book_page_images_page UNIQUE (version_id, page_number),
    CONSTRAINT chk_book_page_images_page CHECK (page_number > 0),
    CONSTRAINT chk_book_page_images_size CHECK (width_px > 0 AND height_px > 0 AND byte_size > 0)
)
SQL);
        $this->addSql('CREATE INDEX idx_book_page_images_version ON book_page_images (version_id, page_number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE book_page_images');
    }
}
