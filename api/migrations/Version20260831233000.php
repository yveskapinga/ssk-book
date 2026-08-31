<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SQL-first book catalog, versioned content, pages, chunks and ingestion jobs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE books (
    id UUID PRIMARY KEY,
    slug VARCHAR(120) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_books_slug CHECK (slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$'),
    CONSTRAINT chk_books_title CHECK (length(btrim(title)) >= 2),
    CONSTRAINT chk_books_status CHECK (status IN ('ACTIVE', 'ARCHIVED'))
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_books_slug ON books (slug)');

        $this->addSql(<<<'SQL'
CREATE TABLE book_versions (
    id UUID PRIMARY KEY,
    book_id UUID NOT NULL REFERENCES books(id) ON DELETE RESTRICT,
    version_number INTEGER NOT NULL,
    label VARCHAR(120) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'UPLOADED',
    source_filename VARCHAR(255) NOT NULL,
    source_path VARCHAR(500) NOT NULL,
    source_sha256 CHAR(64) NOT NULL,
    page_count INTEGER NULL,
    published_at TIMESTAMPTZ NULL,
    created_by UUID NOT NULL REFERENCES app_users(id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_book_version_number UNIQUE (book_id, version_number),
    CONSTRAINT chk_book_version_number CHECK (version_number > 0),
    CONSTRAINT chk_book_version_status CHECK (status IN ('UPLOADED', 'EXTRACTING', 'STRUCTURING', 'EMBEDDING', 'REVIEW_REQUIRED', 'PUBLISHED', 'FAILED')),
    CONSTRAINT chk_book_version_sha CHECK (source_sha256 ~ '^[0-9a-f]{64}$'),
    CONSTRAINT chk_book_version_page_count CHECK (page_count IS NULL OR page_count > 0),
    CONSTRAINT chk_book_version_publication CHECK ((status = 'PUBLISHED' AND published_at IS NOT NULL) OR status <> 'PUBLISHED')
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_book_versions_source_sha ON book_versions (book_id, source_sha256)');
        $this->addSql('CREATE INDEX idx_book_versions_status ON book_versions (status, created_at DESC)');

        $this->addSql(<<<'SQL'
CREATE TABLE book_nodes (
    id UUID PRIMARY KEY,
    version_id UUID NOT NULL REFERENCES book_versions(id) ON DELETE CASCADE,
    parent_id UUID NULL REFERENCES book_nodes(id) ON DELETE CASCADE,
    node_type VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    position INTEGER NOT NULL,
    start_page INTEGER NULL,
    end_page INTEGER NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_book_nodes_type CHECK (node_type IN ('PART', 'CHAPTER', 'SECTION')),
    CONSTRAINT chk_book_nodes_position CHECK (position >= 0),
    CONSTRAINT chk_book_nodes_pages CHECK (start_page IS NULL OR (start_page > 0 AND (end_page IS NULL OR end_page >= start_page)))
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_book_nodes_position ON book_nodes (version_id, parent_id, position) NULLS NOT DISTINCT');
        $this->addSql('CREATE INDEX idx_book_nodes_version ON book_nodes (version_id, position)');
        $this->addSql(<<<'SQL'
CREATE FUNCTION enforce_book_node_parent_version() RETURNS trigger AS $$
BEGIN
    IF NEW.parent_id IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM book_nodes parent
        WHERE parent.id = NEW.parent_id AND parent.version_id = NEW.version_id
    ) THEN
        RAISE EXCEPTION 'A book node parent must belong to the same version';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
        $this->addSql('CREATE TRIGGER trg_book_node_parent_version BEFORE INSERT OR UPDATE ON book_nodes FOR EACH ROW EXECUTE FUNCTION enforce_book_node_parent_version()');

        $this->addSql(<<<'SQL'
CREATE TABLE book_pages (
    id UUID PRIMARY KEY,
    version_id UUID NOT NULL REFERENCES book_versions(id) ON DELETE CASCADE,
    page_number INTEGER NOT NULL,
    printed_page_label VARCHAR(30) NULL,
    raw_text TEXT NOT NULL,
    normalized_text TEXT NOT NULL,
    text_sha256 CHAR(64) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_book_pages_number UNIQUE (version_id, page_number),
    CONSTRAINT chk_book_pages_number CHECK (page_number > 0),
    CONSTRAINT chk_book_pages_sha CHECK (text_sha256 ~ '^[0-9a-f]{64}$')
)
SQL);
        $this->addSql("CREATE INDEX idx_book_pages_search ON book_pages USING GIN (to_tsvector('french', normalized_text))");

        $this->addSql(<<<'SQL'
CREATE TABLE book_chunks (
    id UUID PRIMARY KEY,
    version_id UUID NOT NULL REFERENCES book_versions(id) ON DELETE CASCADE,
    node_id UUID NULL REFERENCES book_nodes(id) ON DELETE SET NULL,
    start_page INTEGER NOT NULL,
    end_page INTEGER NOT NULL,
    position INTEGER NOT NULL,
    content TEXT NOT NULL,
    content_sha256 CHAR(64) NOT NULL,
    token_estimate INTEGER NOT NULL,
    embedding VECTOR(768) NULL,
    embedding_model VARCHAR(120) NULL,
    embedded_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_book_chunks_position UNIQUE (version_id, position),
    CONSTRAINT chk_book_chunks_pages CHECK (start_page > 0 AND end_page >= start_page),
    CONSTRAINT chk_book_chunks_content CHECK (length(btrim(content)) > 0),
    CONSTRAINT chk_book_chunks_sha CHECK (content_sha256 ~ '^[0-9a-f]{64}$'),
    CONSTRAINT chk_book_chunks_tokens CHECK (token_estimate > 0),
    CONSTRAINT chk_book_chunks_embedding_metadata CHECK ((embedding IS NULL AND embedding_model IS NULL AND embedded_at IS NULL) OR (embedding IS NOT NULL AND embedding_model IS NOT NULL AND embedded_at IS NOT NULL))
)
SQL);
        $this->addSql("CREATE INDEX idx_book_chunks_search ON book_chunks USING GIN (to_tsvector('french', content))");
        $this->addSql('CREATE INDEX idx_book_chunks_version_pages ON book_chunks (version_id, start_page, end_page)');

        $this->addSql(<<<'SQL'
CREATE TABLE ingestion_jobs (
    id UUID PRIMARY KEY,
    version_id UUID NOT NULL REFERENCES book_versions(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    current_step VARCHAR(40) NOT NULL DEFAULT 'QUEUED',
    progress SMALLINT NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    metrics JSONB NOT NULL DEFAULT '{}'::jsonb,
    started_at TIMESTAMPTZ NULL,
    completed_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_ingestion_jobs_status CHECK (status IN ('PENDING', 'RUNNING', 'COMPLETED', 'FAILED')),
    CONSTRAINT chk_ingestion_jobs_progress CHECK (progress BETWEEN 0 AND 100),
    CONSTRAINT chk_ingestion_jobs_metrics CHECK (jsonb_typeof(metrics) = 'object')
)
SQL);
        $this->addSql('CREATE INDEX idx_ingestion_jobs_version ON ingestion_jobs (version_id, created_at DESC)');
        $this->addSql("CREATE UNIQUE INDEX uq_ingestion_jobs_active ON ingestion_jobs (version_id) WHERE status IN ('PENDING', 'RUNNING')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS ingestion_jobs');
        $this->addSql('DROP TABLE IF EXISTS book_chunks');
        $this->addSql('DROP TABLE IF EXISTS book_pages');
        $this->addSql('DROP TABLE IF EXISTS book_nodes');
        $this->addSql('DROP FUNCTION IF EXISTS enforce_book_node_parent_version');
        $this->addSql('DROP TABLE IF EXISTS book_versions');
        $this->addSql('DROP TABLE IF EXISTS books');
    }
}
