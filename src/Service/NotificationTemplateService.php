<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NotificationTemplate;
use App\Entity\TestRun;
use App\Repository\NotificationTemplateRepository;
use App\Repository\SettingsRepository;

class NotificationTemplateService
{
    public function __construct(
        private readonly NotificationTemplateRepository $repository,
        private readonly SettingsRepository $settingsRepository,
        private readonly string $allurePublicUrl = '',
    ) {
    }

    /**
     * Get rendered template for a test run.
     *
     * @return array{subject: string|null, body: string|null}
     */
    public function render(TestRun $run, string $channel, string $eventName): array
    {
        $template = $this->repository->findActiveByChannelAndName($channel, $eventName);

        if (!$template) {
            return ['subject' => null, 'body' => null];
        }

        $variables = $this->buildVariables($run);

        return [
            'subject' => $template->getSubject() ? $this->replaceVariables($template->getSubject(), $variables) : null,
            'body' => $this->replaceVariables($template->getBody(), $variables),
        ];
    }

    /**
     * Render template content with sample data for preview.
     *
     * @return array{subject: string|null, body: string}
     */
    public function renderPreview(string $subject, string $body, string $channel): array
    {
        $variables = $this->getSampleVariables();

        return [
            'subject' => $subject ? $this->replaceVariables($subject, $variables) : null,
            'body' => $this->replaceVariables($body, $variables),
        ];
    }

    /**
     * Build variable map from TestRun.
     */
    public function buildVariables(TestRun $run): array
    {
        $counts = $run->getResultCounts();
        $env = $run->getEnvironment();
        $settings = $this->settingsRepository->getSettings();

        return [
            'run_id' => (string) $run->getId(),
            'run_status' => ucfirst($run->getStatus()),
            'environment_name' => $env->getName(),
            'environment_code' => $env->getCode(),
            'test_type' => strtoupper($run->getType()),
            'duration' => $run->getDurationFormatted(),
            'triggered_by' => ucfirst($run->getTriggeredBy()),
            'test_filter' => $run->getTestFilter() ?? '',
            'passed_count' => (string) ($counts['passed'] ?? 0),
            'failed_count' => (string) ($counts['failed'] ?? 0),
            'broken_count' => (string) ($counts['broken'] ?? 0),
            'skipped_count' => (string) ($counts['skipped'] ?? 0),
            'total_count' => (string) ($counts['total'] ?? 0),
            'error_message' => $run->getErrorMessage() ?? '',
            'allure_report_url' => $this->getAllureReportUrl($env->getCode()),
            'site_name' => $settings->getSiteName(),
            'status_emoji' => $this->getStatusEmoji($run),
            'status_color' => $this->getStatusColor($run),
            // Conditional flags
            'has_failures' => ($counts['failed'] ?? 0) > 0,
            'has_filter' => !empty($run->getTestFilter()),
            'has_error' => !empty($run->getErrorMessage()),
            'total_count > 0' => ($counts['total'] ?? 0) > 0,
        ];
    }

    /**
     * Get sample variables for preview.
     */
    public function getSampleVariables(): array
    {
        $settings = $this->settingsRepository->getSettings();

        return [
            'run_id' => '123',
            'run_status' => 'Completed',
            'environment_name' => 'Stage US',
            'environment_code' => 'stage-us',
            'test_type' => 'MFTF',
            'duration' => '5m 23s',
            'triggered_by' => 'Scheduler',
            'test_filter' => '@smoke',
            'passed_count' => '95',
            'failed_count' => '2',
            'broken_count' => '1',
            'skipped_count' => '3',
            'total_count' => '101',
            'error_message' => 'Docker timeout error (sample)',
            'allure_report_url' => 'https://allure.example.com/report',
            'site_name' => $settings->getSiteName(),
            'status_emoji' => ':warning:',
            'status_color' => 'warning',
            'has_failures' => true,
            'has_filter' => true,
            'has_error' => true,
            'total_count > 0' => true,
        ];
    }

    /**
     * Get list of available variables with descriptions.
     */
    public function getAvailableVariables(): array
    {
        return [
            ['name' => 'run_id', 'description' => 'Test run ID'],
            ['name' => 'run_status', 'description' => 'Status (Completed, Failed, Cancelled)'],
            ['name' => 'environment_name', 'description' => 'Target environment name'],
            ['name' => 'environment_code', 'description' => 'Environment code (staging-es)'],
            ['name' => 'test_type', 'description' => 'Test type (MFTF, Playwright)'],
            ['name' => 'duration', 'description' => 'Formatted duration (5m 23s)'],
            ['name' => 'triggered_by', 'description' => 'Trigger source (Manual, Scheduler, API)'],
            ['name' => 'test_filter', 'description' => 'Applied test filter'],
            ['name' => 'passed_count', 'description' => 'Number of passed tests'],
            ['name' => 'failed_count', 'description' => 'Number of failed tests'],
            ['name' => 'broken_count', 'description' => 'Number of broken tests'],
            ['name' => 'skipped_count', 'description' => 'Number of skipped tests'],
            ['name' => 'total_count', 'description' => 'Total test count'],
            ['name' => 'error_message', 'description' => 'Error message (if failed)'],
            ['name' => 'allure_report_url', 'description' => 'Link to Allure report'],
            ['name' => 'site_name', 'description' => 'Site name from settings'],
            ['name' => 'status_emoji', 'description' => 'Slack emoji for status'],
            ['name' => 'status_color', 'description' => 'Color code (good/warning/danger)'],
        ];
    }

    /**
     * Get default template content for reset.
     */
    public function getDefaultTemplateContent(string $channel, string $name): array
    {
        $defaults = $this->getDefaultTemplates();
        $key = "{$channel}_{$name}";

        return $defaults[$key] ?? ['subject' => null, 'body' => ''];
    }

    /**
     * Determine event name from TestRun status.
     */
    public function getEventName(TestRun $run): string
    {
        return match ($run->getStatus()) {
            TestRun::STATUS_COMPLETED => ($run->getResultCounts()['failed'] ?? 0) > 0
                ? NotificationTemplate::NAME_COMPLETED_FAILURES
                : NotificationTemplate::NAME_COMPLETED_SUCCESS,
            TestRun::STATUS_FAILED => NotificationTemplate::NAME_FAILED,
            TestRun::STATUS_CANCELLED => NotificationTemplate::NAME_CANCELLED,
            default => NotificationTemplate::NAME_COMPLETED_SUCCESS,
        };
    }

    /**
     * Replace {{ variable }} placeholders and process conditionals.
     */
    private function replaceVariables(string $template, array $variables): string
    {
        // Process conditionals first: {% if condition %}...{% endif %}
        $result = preg_replace_callback(
            '/\{%\s*if\s+([a-z_0-9 >]+)\s*%\}(.*?)\{%\s*endif\s*%\}/s',
            function ($matches) use ($variables) {
                $condition = trim($matches[1]);
                $content = $matches[2];

                // Handle "total_count > 0" style conditions
                if (isset($variables[$condition])) {
                    return !empty($variables[$condition]) ? $content : '';
                }

                // Handle simple variable conditions
                return !empty($variables[$condition]) ? $content : '';
            },
            $template,
        );

        // Replace simple variables: {{ var_name }}
        $result = preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn ($m) => $variables[$m[1]] ?? '',
            $result,
        );

        return $result;
    }

    private function getStatusEmoji(TestRun $run): string
    {
        $status = $run->getStatus();
        $counts = $run->getResultCounts();

        return match ($status) {
            TestRun::STATUS_COMPLETED => ($counts['failed'] ?? 0) > 0 ? ':warning:' : ':white_check_mark:',
            TestRun::STATUS_FAILED => ':x:',
            TestRun::STATUS_CANCELLED => ':no_entry:',
            default => ':hourglass:',
        };
    }

    private function getStatusColor(TestRun $run): string
    {
        $status = $run->getStatus();
        $counts = $run->getResultCounts();

        return match ($status) {
            TestRun::STATUS_COMPLETED => ($counts['failed'] ?? 0) > 0 ? 'warning' : 'good',
            TestRun::STATUS_FAILED => 'danger',
            default => '#808080',
        };
    }

    private function getAllureReportUrl(string $envName): string
    {
        if (empty($this->allurePublicUrl)) {
            return '';
        }

        return rtrim($this->allurePublicUrl, '/') . '/allure-docker-service/projects/' . $envName . '/reports/latest/index.html';
    }

    private function getDefaultTemplates(): array
    {
        return [
            'slack_test_run_completed_success' => [
                'subject' => null,
                'body' => $this->getSlackSuccessBody(),
            ],
            'slack_test_run_completed_failures' => [
                'subject' => null,
                'body' => $this->getSlackFailuresBody(),
            ],
            'slack_test_run_failed' => [
                'subject' => null,
                'body' => $this->getSlackFailedBody(),
            ],
            'slack_test_run_cancelled' => [
                'subject' => null,
                'body' => $this->getSlackCancelledBody(),
            ],
            'email_test_run_completed_success' => [
                'subject' => '✅ Test Run #{{ run_id }} - {{ run_status }} ({{ environment_name }})',
                'body' => $this->getEmailSuccessBody(),
            ],
            'email_test_run_completed_failures' => [
                'subject' => '⚠️ Test Run #{{ run_id }} - {{ run_status }} ({{ environment_name }})',
                'body' => $this->getEmailFailuresBody(),
            ],
            'email_test_run_failed' => [
                'subject' => '❌ Test Run #{{ run_id }} - FAILED ({{ environment_name }})',
                'body' => $this->getEmailFailedBody(),
            ],
            'email_test_run_cancelled' => [
                'subject' => '🚫 Test Run #{{ run_id }} - Cancelled ({{ environment_name }})',
                'body' => $this->getEmailCancelledBody(),
            ],
        ];
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

            {% if total_count > 0 %}
            *Results:* {{ passed_count }} passed | {{ failed_count }} failed | {{ broken_count }} broken | {{ skipped_count }} skipped
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

            {% if total_count > 0 %}
            *Partial Results:* {{ passed_count }} passed | {{ failed_count }} failed | {{ broken_count }} broken | {{ skipped_count }} skipped
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

            {% if total_count > 0 %}
            <h3>Results</h3>
            <ul style="list-style: none; padding: 0;">
              <li>✅ Passed: {{ passed_count }}</li>
              <li style="color: #dc3545;">❌ <strong>Failed: {{ failed_count }}</strong></li>
              <li>⏭️ Skipped: {{ skipped_count }}</li>
              <li>💔 Broken: {{ broken_count }}</li>
            </ul>
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

            {% if total_count > 0 %}
            <h3>Partial Results</h3>
            <ul style="list-style: none; padding: 0;">
              <li>✅ Passed: {{ passed_count }}</li>
              <li>❌ Failed: {{ failed_count }}</li>
              <li>⏭️ Skipped: {{ skipped_count }}</li>
              <li>💔 Broken: {{ broken_count }}</li>
            </ul>
            {% endif %}

            <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
            <p style="color: #666; font-size: 12px;">{{ site_name }} - Automation Test Runner</p>
            </body>
            </html>
            HTML;
    }
}
