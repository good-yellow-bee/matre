<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create notification_templates table with default templates.
 */
final class Version20260119120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notification templates table with 8 default templates (4 events x 2 channels)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE matre_notification_templates (
            id INT AUTO_INCREMENT NOT NULL,
            channel VARCHAR(20) NOT NULL,
            name VARCHAR(100) NOT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            body LONGTEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_CHANNEL_NAME (channel, name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $now = date('Y-m-d H:i:s');

        // Slack templates
        $this->addSql("INSERT INTO matre_notification_templates (channel, name, subject, body, is_active, is_default, created_at) VALUES
            ('slack', 'test_run_completed_success', NULL, " . $this->connection->quote($this->getSlackSuccessBody()) . ", 1, 1, '$now'),
            ('slack', 'test_run_completed_failures', NULL, " . $this->connection->quote($this->getSlackFailuresBody()) . ", 1, 1, '$now'),
            ('slack', 'test_run_failed', NULL, " . $this->connection->quote($this->getSlackFailedBody()) . ", 1, 1, '$now'),
            ('slack', 'test_run_cancelled', NULL, " . $this->connection->quote($this->getSlackCancelledBody()) . ", 1, 1, '$now')");

        // Email templates
        $this->addSql("INSERT INTO matre_notification_templates (channel, name, subject, body, is_active, is_default, created_at) VALUES
            ('email', 'test_run_completed_success', '✅ Test Run #{{ run_id }} - {{ run_status }} ({{ environment_name }})', " . $this->connection->quote($this->getEmailSuccessBody()) . ", 1, 1, '$now'),
            ('email', 'test_run_completed_failures', '⚠️ Test Run #{{ run_id }} - {{ run_status }} ({{ environment_name }})', " . $this->connection->quote($this->getEmailFailuresBody()) . ", 1, 1, '$now'),
            ('email', 'test_run_failed', '❌ Test Run #{{ run_id }} - FAILED ({{ environment_name }})', " . $this->connection->quote($this->getEmailFailedBody()) . ", 1, 1, '$now'),
            ('email', 'test_run_cancelled', '🚫 Test Run #{{ run_id }} - Cancelled ({{ environment_name }})', " . $this->connection->quote($this->getEmailCancelledBody()) . ", 1, 1, '$now')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE matre_notification_templates');
    }

    private function getSlackSuccessBody(): string
    {
        return <<<'SLACK'
{{ status_emoji }} *Test Run #{{ run_id }} {{ run_status }}*

*Environment:* {{ environment_name }}
*Type:* {{ test_type }}
*Status:* {{ run_status }}
*Duration:* {{ duration }}
*Triggered By:* {{ triggered_by }}

{% if total_count > 0 %}
*Results:* {{ passed_count }} passed | {{ failed_count }} failed | {{ broken_count }} broken | {{ skipped_count }} skipped
{% endif %}

{% if has_filter %}
*Filter:* {{ test_filter }}
{% endif %}

{% if allure_report_url %}
<{{ allure_report_url }}|View Allure Report>
{% endif %}
SLACK;
    }

    private function getSlackFailuresBody(): string
    {
        return <<<'SLACK'
{{ status_emoji }} *Test Run #{{ run_id }} {{ run_status }}*

*Environment:* {{ environment_name }}
*Type:* {{ test_type }}
*Status:* {{ run_status }}
*Duration:* {{ duration }}
*Triggered By:* {{ triggered_by }}

{% if total_count > 0 %}
*Results:* {{ passed_count }} passed | *{{ failed_count }} failed* | {{ broken_count }} broken | {{ skipped_count }} skipped
{% endif %}

{% if has_filter %}
*Filter:* {{ test_filter }}
{% endif %}

⚠️ *Some tests require attention!*

{% if allure_report_url %}
<{{ allure_report_url }}|View Allure Report>
{% endif %}
SLACK;
    }

    private function getSlackFailedBody(): string
    {
        return <<<'SLACK'
{{ status_emoji }} *Test Run #{{ run_id }} FAILED*

*Environment:* {{ environment_name }}
*Type:* {{ test_type }}
*Status:* Failed
*Triggered By:* {{ triggered_by }}

{% if has_error %}
*Error:* {{ error_message }}
{% endif %}

{% if has_filter %}
*Filter:* {{ test_filter }}
{% endif %}
SLACK;
    }

    private function getSlackCancelledBody(): string
    {
        return <<<'SLACK'
🚫 *Test Run #{{ run_id }} Cancelled*

*Environment:* {{ environment_name }}
*Type:* {{ test_type }}
*Status:* Cancelled
*Triggered By:* {{ triggered_by }}

{% if has_filter %}
*Filter:* {{ test_filter }}
{% endif %}
SLACK;
    }

    private function getEmailSuccessBody(): string
    {
        return <<<'HTML'
<html>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
<h2 style="color: #28a745;">Test Run #{{ run_id }} - {{ run_status }}</h2>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 140px;">Environment</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ environment_name }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Type</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ test_type }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Status</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ run_status }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Duration</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ duration }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Triggered By</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ triggered_by }}</td></tr>
  {% if has_filter %}<tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Filter</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ test_filter }}</td></tr>{% endif %}
</table>

{% if total_count > 0 %}
<h3>Results</h3>
<ul>
  <li>✅ Passed: {{ passed_count }}</li>
  <li>❌ Failed: {{ failed_count }}</li>
  <li>⏭️ Skipped: {{ skipped_count }}</li>
  <li>💔 Broken: {{ broken_count }}</li>
</ul>
{% endif %}

{% if allure_report_url %}
<p><a href="{{ allure_report_url }}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">View Allure Report</a></p>
{% endif %}

<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
<p style="color: #666; font-size: 12px;">{{ site_name }} - Automation Test Runner</p>
</body>
</html>
HTML;
    }

    private function getEmailFailuresBody(): string
    {
        return <<<'HTML'
<html>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
<h2 style="color: #ffc107;">⚠️ Test Run #{{ run_id }} - {{ run_status }}</h2>

<p style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 10px; border-radius: 4px;">
<strong>Attention:</strong> Some tests have failed and require review.
</p>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 140px;">Environment</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ environment_name }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Type</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ test_type }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Status</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ run_status }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Duration</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ duration }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Triggered By</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ triggered_by }}</td></tr>
  {% if has_filter %}<tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Filter</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ test_filter }}</td></tr>{% endif %}
</table>

{% if total_count > 0 %}
<h3>Results</h3>
<ul>
  <li>✅ Passed: {{ passed_count }}</li>
  <li style="color: #dc3545;">❌ <strong>Failed: {{ failed_count }}</strong></li>
  <li>⏭️ Skipped: {{ skipped_count }}</li>
  <li>💔 Broken: {{ broken_count }}</li>
</ul>
{% endif %}

{% if allure_report_url %}
<p><a href="{{ allure_report_url }}" style="background-color: #ffc107; color: #333; padding: 10px 20px; text-decoration: none; border-radius: 4px;">View Allure Report</a></p>
{% endif %}

<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
<p style="color: #666; font-size: 12px;">{{ site_name }} - Automation Test Runner</p>
</body>
</html>
HTML;
    }

    private function getEmailFailedBody(): string
    {
        return <<<'HTML'
<html>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
<h2 style="color: #dc3545;">❌ Test Run #{{ run_id }} - FAILED</h2>

<p style="background-color: #f8d7da; border: 1px solid #dc3545; padding: 10px; border-radius: 4px;">
<strong>Error:</strong> The test run failed to complete due to an execution error.
</p>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 140px;">Environment</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ environment_name }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Type</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ test_type }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Triggered By</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ triggered_by }}</td></tr>
  {% if has_filter %}<tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Filter</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ test_filter }}</td></tr>{% endif %}
</table>

{% if has_error %}
<h3>Error Details</h3>
<pre style="background-color: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto;">{{ error_message }}</pre>
{% endif %}

<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
<p style="color: #666; font-size: 12px;">{{ site_name }} - Automation Test Runner</p>
</body>
</html>
HTML;
    }

    private function getEmailCancelledBody(): string
    {
        return <<<'HTML'
<html>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
<h2 style="color: #6c757d;">🚫 Test Run #{{ run_id }} - Cancelled</h2>

<p style="background-color: #e2e3e5; border: 1px solid #6c757d; padding: 10px; border-radius: 4px;">
This test run was cancelled before completion.
</p>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 140px;">Environment</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ environment_name }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Type</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ test_type }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Triggered By</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ triggered_by }}</td></tr>
  {% if has_filter %}<tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Filter</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ test_filter }}</td></tr>{% endif %}
</table>

<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
<p style="color: #666; font-size: 12px;">{{ site_name }} - Automation Test Runner</p>
</body>
</html>
HTML;
    }
}
