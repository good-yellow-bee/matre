<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260123201629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create audit_logs table for admin changes history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE matre_audit_logs (id INT AUTO_INCREMENT NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id INT NOT NULL, entity_label VARCHAR(255) DEFAULT NULL, action VARCHAR(20) NOT NULL, old_data JSON DEFAULT NULL, new_data JSON DEFAULT NULL, changed_fields JSON DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', user_id INT DEFAULT NULL, INDEX IDX_AUDIT_ENTITY (entity_type, entity_id), INDEX IDX_AUDIT_CREATED (created_at), INDEX IDX_AUDIT_USER (user_id), INDEX IDX_AUDIT_ACTION (action), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql('ALTER TABLE matre_audit_logs ADD CONSTRAINT FK_600C9DC5A76ED395 FOREIGN KEY (user_id) REFERENCES matre_users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_audit_logs DROP FOREIGN KEY FK_600C9DC5A76ED395');
        $this->addSql('DROP TABLE matre_audit_logs');
    }
}
