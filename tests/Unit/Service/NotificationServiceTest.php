<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\TestEnvironment;
use App\Entity\TestRun;
use App\Service\NotificationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class NotificationServiceTest extends TestCase
{
    public function testSendSlackNotificationSkipsWhenNoWebhook(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $service = $this->createService(httpClient: $httpClient, slackWebhookUrl: '');

        $service->sendSlackNotification($this->createTestRun());
    }

    public function testSendSlackNotificationSendsRequest(): void
    {
        $response = $this->createStub(ResponseInterface::class);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'https://hooks.slack.com/test', $this->callback(function ($options) {
                return isset($options['json']) && is_array($options['json']);
            }))
            ->willReturn($response);

        $service = $this->createService(httpClient: $httpClient);

        $service->sendSlackNotification($this->createTestRun());
    }

    public function testSendSlackNotificationRetriesOnFailure(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->exactly(3))
            ->method('request')
            ->willThrowException(new \RuntimeException('Connection timeout'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('warning');
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to send Slack notification after retries', $this->anything());

        $service = $this->createService(httpClient: $httpClient, logger: $logger);

        $service->sendSlackNotification($this->createTestRun());
    }

    public function testSendEmailNotificationSkipsWhenNoRecipients(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $service = $this->createService(mailer: $mailer);

        $service->sendEmailNotification($this->createTestRun(), []);
    }

    public function testSendEmailNotificationSendsEmail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                return 'admin@example.com' === $email->getTo()[0]->getAddress();
            }));

        $service = $this->createService(mailer: $mailer);

        $service->sendEmailNotification($this->createTestRun(), ['admin@example.com']);
    }

    public function testSendEmailNotificationHandlesFailure(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')
            ->willThrowException(new \RuntimeException('SMTP error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to send email notification', $this->anything());

        $service = $this->createService(mailer: $mailer, logger: $logger);

        $service->sendEmailNotification($this->createTestRun(), ['admin@example.com']);
    }

    public function testSendEmailNotificationMultipleRecipients(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                $addresses = array_map(fn ($addr) => $addr->getAddress(), $email->getTo());

                return 2 === count($addresses)
                    && in_array('admin@example.com', $addresses, true)
                    && in_array('user@example.com', $addresses, true);
            }));

        $service = $this->createService(mailer: $mailer);

        $service->sendEmailNotification($this->createTestRun(), ['admin@example.com', 'user@example.com']);
    }

    private function createService(
        ?HttpClientInterface $httpClient = null,
        ?MailerInterface $mailer = null,
        ?LoggerInterface $logger = null,
        string $slackWebhookUrl = 'https://hooks.slack.com/test',
        string $mailFrom = 'noreply@matre.local',
        string $allurePublicUrl = 'http://localhost:5050',
    ): NotificationService {
        return new NotificationService(
            $httpClient ?? $this->createStub(HttpClientInterface::class),
            $mailer ?? $this->createStub(MailerInterface::class),
            $logger ?? $this->createStub(LoggerInterface::class),
            $slackWebhookUrl,
            $mailFrom,
            $allurePublicUrl,
        );
    }

    private function createTestRun(): TestRun
    {
        $env = $this->createStub(TestEnvironment::class);
        $env->method('getName')->willReturn('Staging');
        $env->method('getCode')->willReturn('staging');

        $run = $this->createStub(TestRun::class);
        $run->method('getId')->willReturn(123);
        $run->method('getStatus')->willReturn(TestRun::STATUS_COMPLETED);
        $run->method('getEnvironment')->willReturn($env);
        $run->method('getType')->willReturn('mftf');
        $run->method('getDurationFormatted')->willReturn('5m 30s');
        $run->method('getTriggeredBy')->willReturn('manual');
        $run->method('getResultCounts')->willReturn([
            'total' => 10,
            'passed' => 8,
            'failed' => 2,
            'broken' => 0,
            'skipped' => 0,
        ]);
        $run->method('getTestFilter')->willReturn(null);
        $run->method('getErrorMessage')->willReturn(null);
        $run->method('getSuite')->willReturn(null);

        return $run;
    }
}
