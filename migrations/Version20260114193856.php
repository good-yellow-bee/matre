<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add environments ManyToMany relationship to TestSuite.
 */
final class Version20260114193856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add environments to test suites for targeted scheduled execution';
    }

    public function up(Schema $schema): void
    {
        // Create join table
        $this->addSql('CREATE TABLE matre_test_suite_environments (
            test_suite_id INT NOT NULL,
            test_environment_id INT NOT NULL,
            INDEX IDX_D9DDBCAEDA9FBE4E (test_suite_id),
            INDEX IDX_D9DDBCAEEF72C299 (test_environment_id),
            PRIMARY KEY (test_suite_id, test_environment_id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE matre_test_suite_environments
            ADD CONSTRAINT FK_D9DDBCAEDA9FBE4E
            FOREIGN KEY (test_suite_id) REFERENCES matre_test_suites (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE matre_test_suite_environments
            ADD CONSTRAINT FK_D9DDBCAEEF72C299
            FOREIGN KEY (test_environment_id) REFERENCES matre_test_environments (id) ON DELETE CASCADE');

        // Populate existing scheduled suites with all active environments (preserves current behavior)
        $this->addSql('INSERT INTO matre_test_suite_environments (test_suite_id, test_environment_id)
            SELECT ts.id, te.id
            FROM matre_test_suites ts
            CROSS JOIN matre_test_environments te
            WHERE ts.cron_expression IS NOT NULL AND te.is_active = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_test_suite_environments DROP FOREIGN KEY FK_D9DDBCAEDA9FBE4E');
        $this->addSql('ALTER TABLE matre_test_suite_environments DROP FOREIGN KEY FK_D9DDBCAEEF72C299');
        $this->addSql('DROP TABLE matre_test_suite_environments');
    }
}
