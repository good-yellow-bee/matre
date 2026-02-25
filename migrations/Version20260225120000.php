<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop admin_username and admin_password from test_environments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_test_environments DROP COLUMN admin_username, DROP COLUMN admin_password');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_test_environments ADD admin_username VARCHAR(100) DEFAULT NULL, ADD admin_password VARCHAR(255) DEFAULT NULL');
    }
}
