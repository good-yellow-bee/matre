<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Security Improvements Migration.
 *
 * This migration renames password_reset_requests.token to token_hash
 * to store hashed tokens instead of plain text tokens.
 *
 * Note: Performance indexes are defined in entity attributes and created
 * automatically by earlier migrations.
 */
final class Version20251225000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Security: rename token to token_hash for password reset requests';
    }

    public function up(Schema $schema): void
    {
        // Rename token column to token_hash for password reset requests
        $this->addSql('ALTER TABLE matre_password_reset_requests CHANGE token token_hash VARCHAR(100) NOT NULL');

        // Invalidate all existing password reset tokens (they were stored in plain text)
        $this->addSql('DELETE FROM matre_password_reset_requests WHERE 1=1');
    }

    public function down(Schema $schema): void
    {
        // Revert token_hash back to token
        $this->addSql('ALTER TABLE matre_password_reset_requests CHANGE token_hash token VARCHAR(100) NOT NULL');
    }
}
