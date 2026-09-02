<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reading progress, annotations and SQL-first quiz domain';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE reading_progress (
    user_id UUID NOT NULL REFERENCES app_users(id) ON DELETE CASCADE,
    version_id UUID NOT NULL REFERENCES book_versions(id) ON DELETE CASCADE,
    chunk_id UUID NOT NULL REFERENCES book_chunks(id) ON DELETE CASCADE,
    page_number INTEGER NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, version_id),
    CONSTRAINT chk_reading_progress_page CHECK (page_number > 0)
)
SQL);
        $this->addSql('CREATE INDEX idx_reading_progress_version ON reading_progress (version_id, updated_at DESC)');

        $this->addSql(<<<'SQL'
CREATE TABLE bookmarks (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES app_users(id) ON DELETE CASCADE,
    version_id UUID NOT NULL REFERENCES book_versions(id) ON DELETE CASCADE,
    chunk_id UUID NOT NULL REFERENCES book_chunks(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_bookmarks_user_chunk UNIQUE (user_id, chunk_id)
)
SQL);
        $this->addSql('CREATE INDEX idx_bookmarks_user ON bookmarks (user_id, created_at DESC)');

        $this->addSql(<<<'SQL'
CREATE TABLE highlights (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES app_users(id) ON DELETE CASCADE,
    version_id UUID NOT NULL REFERENCES book_versions(id) ON DELETE CASCADE,
    chunk_id UUID NOT NULL REFERENCES book_chunks(id) ON DELETE CASCADE,
    excerpt TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_highlights_excerpt CHECK (length(btrim(excerpt)) > 0)
)
SQL);
        $this->addSql('CREATE INDEX idx_highlights_user ON highlights (user_id, created_at DESC)');

        $this->addSql(<<<'SQL'
CREATE TABLE reader_notes (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES app_users(id) ON DELETE CASCADE,
    version_id UUID NOT NULL REFERENCES book_versions(id) ON DELETE CASCADE,
    chunk_id UUID NOT NULL REFERENCES book_chunks(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_reader_notes_body CHECK (length(btrim(body)) > 0)
)
SQL);
        $this->addSql('CREATE INDEX idx_reader_notes_user ON reader_notes (user_id, updated_at DESC)');

        $this->addSql(<<<'SQL'
CREATE TABLE quizzes (
    id UUID PRIMARY KEY,
    book_id UUID NOT NULL REFERENCES books(id) ON DELETE RESTRICT,
    version_id UUID NOT NULL REFERENCES book_versions(id) ON DELETE RESTRICT,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    created_by UUID NOT NULL REFERENCES app_users(id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_quizzes_title CHECK (length(btrim(title)) >= 2),
    CONSTRAINT chk_quizzes_status CHECK (status IN ('DRAFT', 'REVIEW_REQUIRED', 'PUBLISHED', 'ARCHIVED'))
)
SQL);
        $this->addSql('CREATE INDEX idx_quizzes_status ON quizzes (status, created_at DESC)');

        $this->addSql(<<<'SQL'
CREATE TABLE quiz_questions (
    id UUID PRIMARY KEY,
    quiz_id UUID NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
    position INTEGER NOT NULL,
    prompt TEXT NOT NULL,
    question_type VARCHAR(20) NOT NULL,
    chunk_id UUID NULL REFERENCES book_chunks(id) ON DELETE SET NULL,
    CONSTRAINT uq_quiz_questions_position UNIQUE (quiz_id, position),
    CONSTRAINT chk_quiz_questions_position CHECK (position >= 0),
    CONSTRAINT chk_quiz_questions_prompt CHECK (length(btrim(prompt)) >= 3),
    CONSTRAINT chk_quiz_questions_type CHECK (question_type IN ('MULTIPLE_CHOICE', 'TRUE_FALSE', 'SHORT_ANSWER'))
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE quiz_choices (
    id UUID PRIMARY KEY,
    question_id UUID NOT NULL REFERENCES quiz_questions(id) ON DELETE CASCADE,
    position INTEGER NOT NULL,
    label TEXT NOT NULL,
    is_correct BOOLEAN NOT NULL,
    CONSTRAINT uq_quiz_choices_position UNIQUE (question_id, position),
    CONSTRAINT chk_quiz_choices_position CHECK (position >= 0),
    CONSTRAINT chk_quiz_choices_label CHECK (length(btrim(label)) > 0)
)
SQL);

        $this->addSql(<<<'SQL'
CREATE FUNCTION enforce_quiz_single_correct_choice() RETURNS trigger AS $$
DECLARE
    question_kind VARCHAR(20);
    correct_count INTEGER;
BEGIN
    SELECT question_type INTO question_kind FROM quiz_questions WHERE id = COALESCE(NEW.question_id, OLD.question_id);
    IF question_kind IN ('MULTIPLE_CHOICE', 'TRUE_FALSE') THEN
        SELECT COUNT(*) FILTER (WHERE is_correct) INTO correct_count
        FROM quiz_choices WHERE question_id = COALESCE(NEW.question_id, OLD.question_id);
        IF correct_count > 1 THEN
            RAISE EXCEPTION 'A closed question must have exactly one correct choice';
        END IF;
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
        $this->addSql('CREATE CONSTRAINT TRIGGER trg_quiz_single_correct AFTER INSERT OR UPDATE OR DELETE ON quiz_choices DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION enforce_quiz_single_correct_choice()');

        $this->addSql(<<<'SQL'
CREATE TABLE quiz_attempts (
    id UUID PRIMARY KEY,
    quiz_id UUID NOT NULL REFERENCES quizzes(id) ON DELETE RESTRICT,
    user_id UUID NOT NULL REFERENCES app_users(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'IN_PROGRESS',
    score NUMERIC(5,2) NULL,
    started_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMPTZ NULL,
    CONSTRAINT chk_quiz_attempts_status CHECK (status IN ('IN_PROGRESS', 'COMPLETED')),
    CONSTRAINT chk_quiz_attempts_score CHECK (score IS NULL OR (score >= 0 AND score <= 100)),
    CONSTRAINT chk_quiz_attempts_completion CHECK ((status = 'COMPLETED' AND completed_at IS NOT NULL AND score IS NOT NULL) OR status = 'IN_PROGRESS')
)
SQL);
        $this->addSql('CREATE INDEX idx_quiz_attempts_user ON quiz_attempts (user_id, started_at DESC)');

        $this->addSql(<<<'SQL'
CREATE TABLE quiz_answers (
    id UUID PRIMARY KEY,
    attempt_id UUID NOT NULL REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    question_id UUID NOT NULL REFERENCES quiz_questions(id) ON DELETE RESTRICT,
    choice_id UUID NULL REFERENCES quiz_choices(id) ON DELETE RESTRICT,
    short_answer TEXT NULL,
    is_correct BOOLEAN NOT NULL,
    CONSTRAINT uq_quiz_answers_question UNIQUE (attempt_id, question_id),
    CONSTRAINT chk_quiz_answers_payload CHECK (choice_id IS NOT NULL OR (short_answer IS NOT NULL AND length(btrim(short_answer)) > 0))
)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS quiz_answers');
        $this->addSql('DROP TABLE IF EXISTS quiz_attempts');
        $this->addSql('DROP TRIGGER IF EXISTS trg_quiz_single_correct ON quiz_choices');
        $this->addSql('DROP FUNCTION IF EXISTS enforce_quiz_single_correct_choice');
        $this->addSql('DROP TABLE IF EXISTS quiz_choices');
        $this->addSql('DROP TABLE IF EXISTS quiz_questions');
        $this->addSql('DROP TABLE IF EXISTS quizzes');
        $this->addSql('DROP TABLE IF EXISTS reader_notes');
        $this->addSql('DROP TABLE IF EXISTS highlights');
        $this->addSql('DROP TABLE IF EXISTS bookmarks');
        $this->addSql('DROP TABLE IF EXISTS reading_progress');
    }
}
