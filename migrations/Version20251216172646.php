<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251216172646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-user notification preferences (email/slack channels, trigger, environments)';
    }

    public function up(Schema $schema): void
    {
        // User notification preferences
        $this->addSql('ALTER TABLE matre_users ADD notifications_enabled TINYINT(1) DEFAULT 0 NOT NULL, ADD notification_trigger VARCHAR(20) DEFAULT \'failures\' NOT NULL, ADD notify_by_email TINYINT(1) DEFAULT 1 NOT NULL, ADD notify_by_slack TINYINT(1) DEFAULT 1 NOT NULL');

        // M2M join table for user notification environments
        $this->addSql('CREATE TABLE matre_user_notification_environments (user_id INT NOT NULL, test_environment_id INT NOT NULL, INDEX IDX_FC3DCBE9A76ED395 (user_id), INDEX IDX_FC3DCBE9EF72C299 (test_environment_id), PRIMARY KEY (user_id, test_environment_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE matre_user_notification_environments ADD CONSTRAINT FK_FC3DCBE9A76ED395 FOREIGN KEY (user_id) REFERENCES matre_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE matre_user_notification_environments ADD CONSTRAINT FK_FC3DCBE9EF72C299 FOREIGN KEY (test_environment_id) REFERENCES matre_test_environments (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_user_notification_environments DROP FOREIGN KEY FK_FC3DCBE9A76ED395');
        $this->addSql('ALTER TABLE matre_user_notification_environments DROP FOREIGN KEY FK_FC3DCBE9EF72C299');
        $this->addSql('DROP TABLE matre_user_notification_environments');
        $this->addSql('ALTER TABLE matre_users DROP notifications_enabled, DROP notification_trigger, DROP notify_by_email, DROP notify_by_slack');
    }
}
