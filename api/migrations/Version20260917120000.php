<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reading passages and sequential dwell progress';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE book_passages (
    id UUID PRIMARY KEY,
    version_id UUID NOT NULL REFERENCES book_versions(id) ON DELETE CASCADE,
    position INTEGER NOT NULL,
    page_number INTEGER NOT NULL,
    node_id UUID NULL REFERENCES book_nodes(id) ON DELETE SET NULL,
    chunk_id UUID NULL REFERENCES book_chunks(id) ON DELETE SET NULL,
    figure_id UUID NULL REFERENCES book_page_images(id) ON DELETE CASCADE,
    kind VARCHAR(10) NOT NULL,
    body TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_book_passages_position UNIQUE (version_id, position),
    CONSTRAINT chk_book_passages_position CHECK (position >= 0),
    CONSTRAINT chk_book_passages_page CHECK (page_number > 0),
    CONSTRAINT chk_book_passages_kind CHECK (kind IN ('TEXT', 'IMAGE')),
    CONSTRAINT chk_book_passages_payload CHECK (
        (kind = 'TEXT' AND length(btrim(body)) > 0 AND figure_id IS NULL)
        OR (kind = 'IMAGE' AND figure_id IS NOT NULL)
    )
)
SQL);
        $this->addSql('CREATE INDEX idx_book_passages_version_page ON book_passages (version_id, page_number, position)');
        $this->addSql('CREATE INDEX idx_book_passages_chunk ON book_passages (chunk_id)');

        $this->addSql(<<<'SQL'
CREATE TABLE reading_passage_progress (
    user_id UUID NOT NULL REFERENCES app_users(id) ON DELETE CASCADE,
    passage_id UUID NOT NULL REFERENCES book_passages(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'IN_PROGRESS',
    displayed_ms INTEGER NOT NULL DEFAULT 0,
    confidence NUMERIC(4,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, passage_id),
    CONSTRAINT chk_reading_passage_status CHECK (status IN ('IN_PROGRESS', 'READ')),
    CONSTRAINT chk_reading_passage_ms CHECK (displayed_ms >= 0),
    CONSTRAINT chk_reading_passage_confidence CHECK (confidence >= 0 AND confidence <= 1)
)
SQL);
        $this->addSql('CREATE INDEX idx_reading_passage_progress_status ON reading_passage_progress (user_id, status)');

        $this->addSql('ALTER TABLE reading_progress ADD COLUMN IF NOT EXISTS passage_id UUID NULL');
        $this->addSql('ALTER TABLE reading_progress DROP CONSTRAINT IF EXISTS fk_reading_progress_passage');
        $this->addSql('ALTER TABLE reading_progress ADD CONSTRAINT fk_reading_progress_passage FOREIGN KEY (passage_id) REFERENCES book_passages(id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reading_progress DROP CONSTRAINT IF EXISTS fk_reading_progress_passage');
        $this->addSql('ALTER TABLE reading_progress DROP COLUMN IF EXISTS passage_id');
        $this->addSql('DROP TABLE IF EXISTS reading_passage_progress');
        $this->addSql('DROP TABLE IF EXISTS book_passages');
    }
}
