<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TestRun;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends notifications via Slack and Email.
 */
class NotificationService
{
    private const MAX_RETRIES = 3;
    private const INITIAL_RETRY_DELAY_MS = 500;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $slackWebhookUrl,
    ) {
    }

    /**
     * Send Slack notification for test run (with retry).
     */
    public function sendSlackNotification(TestRun $run): void
    {
        if (empty($this->slackWebhookUrl)) {
            $this->logger->debug('Slack webhook not configured, skipping notification');

            return;
        }

        $this->logger->info('Sending Slack notification', ['runId' => $run->getId()]);

        $message = $this->buildSlackMessage($run);

        try {
            $this->executeWithRetry(
                fn () => $this->httpClient->request('POST', $this->slackWebhookUrl, [
                    'json' => $message,
                ]),
                'slack_notification',
            );

            $this->logger->info('Slack notification sent');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Slack notification after retries', [
                'runId' => $run->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email notification for test run.
     */
    public function sendEmailNotification(TestRun $run, array $recipients): void
    {
        if (empty($recipients)) {
            return;
        }

        $this->logger->info('Sending email notification', [
            'runId' => $run->getId(),
            'recipients' => $recipients,
        ]);

        $subject = $this->buildEmailSubject($run);
        $body = $this->buildEmailBody($run);

        try {
            $email = (new Email())
                ->subject($subject)
                ->html($body);

            foreach ($recipients as $recipient) {
                $email->addTo($recipient);
            }

            $this->mailer->send($email);

            $this->logger->info('Email notification sent');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Execute HTTP request with exponential backoff retry.
     *
     * @throws \Throwable On final failure after all retries
     */
    private function executeWithRetry(callable $requestFn, string $operation): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; ++$attempt) {
            try {
                return $requestFn();
            } catch (\Throwable $e) {
                $lastException = $e;
                $delayMs = self::INITIAL_RETRY_DELAY_MS * (2 ** $attempt);

                $this->logger->warning('HTTP request failed, retrying', [
                    'operation' => $operation,
                    'attempt' => $attempt + 1,
                    'maxRetries' => self::MAX_RETRIES,
                    'delayMs' => $delayMs,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < self::MAX_RETRIES - 1) {
                    usleep($delayMs * 1000);
                }
            }
        }

        throw $lastException;
    }

    /**
     * Build Slack message payload.
     */
    private function buildSlackMessage(TestRun $run): array
    {
        $status = $run->getStatus();
        $env = $run->getEnvironment();
        $counts = $run->getResultCounts();

        $emoji = match ($status) {
            TestRun::STATUS_COMPLETED => $counts['failed'] > 0 ? ':warning:' : ':white_check_mark:',
            TestRun::STATUS_FAILED => ':x:',
            TestRun::STATUS_CANCELLED => ':no_entry:',
            default => ':hourglass:',
        };

        $color = match ($status) {
            TestRun::STATUS_COMPLETED => $counts['failed'] > 0 ? 'warning' : 'good',
            TestRun::STATUS_FAILED => 'danger',
            default => '#808080',
        };

        $fields = [
            [
                'title' => 'Environment',
                'value' => $env->getName(),
                'short' => true,
            ],
            [
                'title' => 'Type',
                'value' => strtoupper($run->getType()),
                'short' => true,
            ],
            [
                'title' => 'Status',
                'value' => ucfirst($status),
                'short' => true,
            ],
            [
                'title' => 'Duration',
                'value' => $run->getDurationFormatted(),
                'short' => true,
            ],
        ];

        if ($counts['total'] > 0) {
            $fields[] = [
                'title' => 'Results',
                'value' => sprintf(
                    '✅ %d passed | ❌ %d failed | ⏭ %d skipped',
                    $counts['passed'],
                    $counts['failed'],
                    $counts['skipped'],
                ),
                'short' => false,
            ];
        }

        if ($run->getTestFilter()) {
            $fields[] = [
                'title' => 'Filter',
                'value' => $run->getTestFilter(),
                'short' => false,
            ];
        }

        // Add report link if available
        $reports = $run->getReports();
        if (!$reports->isEmpty()) {
            $report = $reports->first();
            if ($report && $report->getPublicUrl()) {
                $fields[] = [
                    'title' => 'Report',
                    'value' => '<' . $report->getPublicUrl() . '|View Allure Report>',
                    'short' => false,
                ];
            }
        }

        return [
            'attachments' => [
                [
                    'color' => $color,
                    'pretext' => $emoji . ' Test Run #' . $run->getId() . ' ' . $status,
                    'fields' => $fields,
                    'footer' => 'ATR - Automation Test Runner',
                    'ts' => time(),
                ],
            ],
        ];
    }

    /**
     * Build email subject.
     */
    private function buildEmailSubject(TestRun $run): string
    {
        $status = $run->getStatus();
        $counts = $run->getResultCounts();

        $prefix = match ($status) {
            TestRun::STATUS_COMPLETED => $counts['failed'] > 0 ? '⚠️' : '✅',
            TestRun::STATUS_FAILED => '❌',
            TestRun::STATUS_CANCELLED => '🚫',
            default => '🔄',
        };

        return sprintf(
            '%s Test Run #%d - %s (%s)',
            $prefix,
            $run->getId(),
            ucfirst($status),
            $run->getEnvironment()->getName(),
        );
    }

    /**
     * Build email body.
     */
    private function buildEmailBody(TestRun $run): string
    {
        $env = $run->getEnvironment();
        $counts = $run->getResultCounts();

        $html = '<html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';

        $html .= '<h2>Test Run #' . $run->getId() . '</h2>';

        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= $this->tableRow('Environment', $env->getName());
        $html .= $this->tableRow('Type', strtoupper($run->getType()));
        $html .= $this->tableRow('Status', ucfirst($run->getStatus()));
        $html .= $this->tableRow('Duration', $run->getDurationFormatted());
        $html .= $this->tableRow('Triggered By', ucfirst($run->getTriggeredBy()));

        if ($run->getTestFilter()) {
            $html .= $this->tableRow('Filter', $run->getTestFilter());
        }

        $html .= '</table>';

        if ($counts['total'] > 0) {
            $html .= '<h3>Results</h3>';
            $html .= '<ul>';
            $html .= '<li>✅ Passed: ' . $counts['passed'] . '</li>';
            $html .= '<li>❌ Failed: ' . $counts['failed'] . '</li>';
            $html .= '<li>⏭ Skipped: ' . $counts['skipped'] . '</li>';
            $html .= '<li>🔧 Broken: ' . $counts['broken'] . '</li>';
            $html .= '</ul>';
        }

        // Add report link
        $reports = $run->getReports();
        if (!$reports->isEmpty()) {
            $report = $reports->first();
            if ($report && $report->getPublicUrl()) {
                $html .= '<p><a href="' . htmlspecialchars($report->getPublicUrl()) . '">View Allure Report</a></p>';
            }
        }

        if ($run->getErrorMessage()) {
            $html .= '<h3 style="color: red;">Error</h3>';
            $html .= '<pre style="background: #f5f5f5; padding: 10px;">' . htmlspecialchars($run->getErrorMessage()) . '</pre>';
        }

        $html .= '<hr><p style="color: #666; font-size: 12px;">ATR - Automation Test Runner</p>';
        $html .= '</body></html>';

        return $html;
    }

    private function tableRow(string $label, string $value): string
    {
        return sprintf(
            '<tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">%s</td><td style="padding: 8px; border-bottom: 1px solid #eee;">%s</td></tr>',
            htmlspecialchars($label),
            htmlspecialchars($value),
        );
    }
}
