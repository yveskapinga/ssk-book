<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SQL-first identity, access token and audit foundation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE app_users (
    id UUID PRIMARY KEY,
    email VARCHAR(180) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    roles JSONB NOT NULL DEFAULT '["ROLE_USER"]'::jsonb,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMPTZ NULL,
    CONSTRAINT chk_app_users_email_normalized CHECK (email = lower(btrim(email))),
    CONSTRAINT chk_app_users_email_not_empty CHECK (length(email) > 3),
    CONSTRAINT chk_app_users_display_name_not_empty CHECK (length(btrim(display_name)) > 1),
    CONSTRAINT chk_app_users_roles_array CHECK (jsonb_typeof(roles) = 'array'),
    CONSTRAINT chk_app_users_status CHECK (status IN ('ACTIVE', 'SUSPENDED'))
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_app_users_email ON app_users (email)');
        $this->addSql('CREATE INDEX idx_app_users_status ON app_users (status)');

        $this->addSql(<<<'SQL'
CREATE TABLE user_access_tokens (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES app_users(id) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    last_used_at TIMESTAMPTZ NULL,
    revoked_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_access_token_expiry CHECK (expires_at > created_at)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_user_access_tokens_hash ON user_access_tokens (token_hash)');
        $this->addSql('CREATE INDEX idx_user_access_tokens_active ON user_access_tokens (expires_at) WHERE revoked_at IS NULL');
        $this->addSql('CREATE INDEX idx_user_access_tokens_user ON user_access_tokens (user_id)');

        $this->addSql(<<<'SQL'
CREATE TABLE audit_logs (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    actor_user_id UUID NULL REFERENCES app_users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    subject_type VARCHAR(80) NOT NULL,
    subject_id VARCHAR(100) NULL,
    context JSONB NOT NULL DEFAULT '{}'::jsonb,
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_audit_action_not_empty CHECK (length(btrim(action)) > 0),
    CONSTRAINT chk_audit_subject_not_empty CHECK (length(btrim(subject_type)) > 0),
    CONSTRAINT chk_audit_context_object CHECK (jsonb_typeof(context) = 'object')
)
SQL);
        $this->addSql('CREATE INDEX idx_audit_logs_actor_time ON audit_logs (actor_user_id, occurred_at DESC)');
        $this->addSql('CREATE INDEX idx_audit_logs_subject ON audit_logs (subject_type, subject_id, occurred_at DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS audit_logs');
        $this->addSql('DROP TABLE IF EXISTS user_access_tokens');
        $this->addSql('DROP TABLE IF EXISTS app_users');
    }
}
