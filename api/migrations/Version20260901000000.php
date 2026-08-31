<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Editorial review, current publication and immutable published content';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE books ADD COLUMN current_version_id UUID NULL');
        $this->addSql('ALTER TABLE books ADD CONSTRAINT fk_books_current_version FOREIGN KEY (current_version_id) REFERENCES book_versions(id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED');
        $this->addSql(<<<'SQL'
CREATE FUNCTION enforce_current_version_book() RETURNS trigger AS $$
BEGIN
    IF NEW.current_version_id IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM book_versions v WHERE v.id = NEW.current_version_id AND v.book_id = NEW.id
    ) THEN
        RAISE EXCEPTION 'Current version must belong to the same book';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
        $this->addSql('CREATE TRIGGER trg_books_current_version BEFORE INSERT OR UPDATE OF current_version_id ON books FOR EACH ROW EXECUTE FUNCTION enforce_current_version_book()');

        $this->addSql(<<<'SQL'
CREATE TABLE book_version_reviews (
    id UUID PRIMARY KEY,
    version_id UUID NOT NULL REFERENCES book_versions(id) ON DELETE CASCADE,
    reviewer_id UUID NOT NULL REFERENCES app_users(id) ON DELETE RESTRICT,
    decision VARCHAR(20) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_book_version_review_decision CHECK (decision IN ('APPROVED', 'REJECTED')),
    CONSTRAINT chk_book_version_review_notes CHECK (notes IS NULL OR length(btrim(notes)) > 0)
)
SQL);
        $this->addSql('CREATE INDEX idx_book_version_reviews_version ON book_version_reviews (version_id, created_at DESC)');

        $this->addSql(<<<'SQL'
CREATE TABLE conversations (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES app_users(id) ON DELETE CASCADE,
    book_id UUID NOT NULL REFERENCES books(id) ON DELETE RESTRICT,
    title VARCHAR(160) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_conversations_title CHECK (length(btrim(title)) > 0)
)
SQL);
        $this->addSql('CREATE INDEX idx_conversations_user ON conversations (user_id, updated_at DESC)');
        $this->addSql(<<<'SQL'
CREATE TABLE conversation_messages (
    id UUID PRIMARY KEY,
    conversation_id UUID NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    role VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    insufficient_evidence BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_conversation_message_role CHECK (role IN ('USER', 'ASSISTANT')),
    CONSTRAINT chk_conversation_message_content CHECK (length(btrim(content)) > 0)
)
SQL);
        $this->addSql('CREATE INDEX idx_conversation_messages_order ON conversation_messages (conversation_id, created_at)');
        $this->addSql(<<<'SQL'
CREATE TABLE message_sources (
    message_id UUID NOT NULL REFERENCES conversation_messages(id) ON DELETE CASCADE,
    chunk_id UUID NOT NULL REFERENCES book_chunks(id) ON DELETE RESTRICT,
    rank SMALLINT NOT NULL,
    score DOUBLE PRECISION NOT NULL,
    PRIMARY KEY (message_id, chunk_id),
    CONSTRAINT chk_message_sources_rank CHECK (rank > 0),
    CONSTRAINT chk_message_sources_score CHECK (score >= 0)
)
SQL);

        $this->addSql(<<<'SQL'
CREATE FUNCTION protect_published_book_content() RETURNS trigger AS $$
DECLARE target_version UUID;
BEGIN
    target_version := CASE WHEN TG_OP = 'DELETE' THEN OLD.version_id ELSE NEW.version_id END;
    IF EXISTS (SELECT 1 FROM book_versions WHERE id = target_version AND status = 'PUBLISHED') THEN
        RAISE EXCEPTION 'Published book content is immutable';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
        $this->addSql('CREATE TRIGGER trg_protect_published_pages BEFORE INSERT OR UPDATE OR DELETE ON book_pages FOR EACH ROW EXECUTE FUNCTION protect_published_book_content()');
        $this->addSql('CREATE TRIGGER trg_protect_published_chunks BEFORE INSERT OR UPDATE OR DELETE ON book_chunks FOR EACH ROW EXECUTE FUNCTION protect_published_book_content()');
        $this->addSql('CREATE TRIGGER trg_protect_published_nodes BEFORE INSERT OR UPDATE OR DELETE ON book_nodes FOR EACH ROW EXECUTE FUNCTION protect_published_book_content()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_protect_published_nodes ON book_nodes');
        $this->addSql('DROP TRIGGER IF EXISTS trg_protect_published_chunks ON book_chunks');
        $this->addSql('DROP TRIGGER IF EXISTS trg_protect_published_pages ON book_pages');
        $this->addSql('DROP FUNCTION IF EXISTS protect_published_book_content');
        $this->addSql('DROP TABLE IF EXISTS book_version_reviews');
        $this->addSql('DROP TABLE IF EXISTS message_sources');
        $this->addSql('DROP TABLE IF EXISTS conversation_messages');
        $this->addSql('DROP TABLE IF EXISTS conversations');
        $this->addSql('ALTER TABLE books DROP CONSTRAINT IF EXISTS fk_books_current_version');
        $this->addSql('DROP TRIGGER IF EXISTS trg_books_current_version ON books');
        $this->addSql('DROP FUNCTION IF EXISTS enforce_current_version_book');
        $this->addSql('ALTER TABLE books DROP COLUMN IF EXISTS current_version_id');
    }
}
