<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251230052458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE matre_password_reset_requests RENAME INDEX uniq_b3ef78a75f37a13b TO UNIQ_B3EF78A7B3BC57DA');
        $this->addSql('ALTER TABLE matre_test_suites ADD estimated_duration INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE matre_password_reset_requests RENAME INDEX uniq_b3ef78a7b3bc57da TO UNIQ_B3EF78A75F37A13B');
        $this->addSql('ALTER TABLE matre_test_suites DROP estimated_duration');
    }
}
