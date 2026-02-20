<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\TestEnvironment;
use App\Entity\TestResult;
use App\Entity\TestRun;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Integration test for notification workflow: Slack + email + template.
 */
class NotificationWorkflowTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
    }

    // =====================
    // Slack Notifications
    // =====================

    public function testSlackNotificationSendsPostRequest(): void
    {
        $run = $this->createCompletedTestRun();

        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://hooks.slack.com/test-webhook', $this->callback(function (array $options) {
                $json = $options['json'];
                $this->assertArrayHasKey('attachments', $json);
                $this->assertNotEmpty($json['attachments']);

                return true;
            }))
            ->willReturn($response);

        $service = $this->buildService($httpClient, webhookUrl: 'https://hooks.slack.com/test-webhook');
        $service->sendSlackNotification($run);
    }

    public function testSlackNotificationSkippedWhenNoWebhook(): void
    {
        $run = $this->createCompletedTestRun();

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $service = $this->buildService($httpClient, webhookUrl: '');
        $service->sendSlackNotification($run);
    }

    public function testSlackNotificationColorGoodForAllPassed(): void
    {
        $run = $this->createCompletedTestRun(passedCount: 3, failedCount: 0);

        $capturedPayload = null;
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedPayload, $response) {
                $capturedPayload = $options['json'];

                return $response;
            });

        $service = $this->buildService($httpClient, webhookUrl: 'https://hooks.slack.com/webhook');
        $service->sendSlackNotification($run);

        $this->assertSame('good', $capturedPayload['attachments'][0]['color']);
    }

    public function testSlackNotificationColorWarningForMixed(): void
    {
        $run = $this->createCompletedTestRun(passedCount: 2, failedCount: 1);

        $capturedPayload = null;
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedPayload, $response) {
                $capturedPayload = $options['json'];

                return $response;
            });

        $service = $this->buildService($httpClient, webhookUrl: 'https://hooks.slack.com/webhook');
        $service->sendSlackNotification($run);

        $this->assertSame('warning', $capturedPayload['attachments'][0]['color']);
    }

    public function testSlackNotificationColorDangerForFailed(): void
    {
        $run = $this->createFailedTestRun();

        $capturedPayload = null;
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedPayload, $response) {
                $capturedPayload = $options['json'];

                return $response;
            });

        $service = $this->buildService($httpClient, webhookUrl: 'https://hooks.slack.com/webhook');
        $service->sendSlackNotification($run);

        $this->assertSame('danger', $capturedPayload['attachments'][0]['color']);
    }

    // =====================
    // Email Notifications
    // =====================

    public function testEmailNotificationSendsToAllRecipients(): void
    {
        $run = $this->createCompletedTestRun(passedCount: 3, failedCount: 0);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $tos = $email->getTo();
                $this->assertCount(2, $tos);

                return true;
            }));

        $service = $this->buildService(mailer: $mailer);
        $service->sendEmailNotification($run, ['user1@example.com', 'user2@example.com']);
    }

    public function testEmailNotificationSkippedWithEmptyRecipients(): void
    {
        $run = $this->createCompletedTestRun();

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $service = $this->buildService(mailer: $mailer);
        $service->sendEmailNotification($run, []);
    }

    public function testEmailNotificationBodyContainsDetails(): void
    {
        $run = $this->createCompletedTestRun(passedCount: 3, failedCount: 1);

        $capturedEmail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$capturedEmail) {
                $capturedEmail = $email;
            });

        $service = $this->buildService(mailer: $mailer);
        $service->sendEmailNotification($run, ['test@example.com']);

        $this->assertNotNull($capturedEmail);
        $body = $capturedEmail->getHtmlBody();
        $this->assertStringContainsString('Environment', $body);
        $this->assertStringContainsString('MFTF', $body);
        $this->assertStringContainsString('Passed', $body);
    }

    // =====================
    // Retry Logic
    // =====================

    public function testSlackNotificationRetriesOnFailure(): void
    {
        $run = $this->createCompletedTestRun();

        $callCount = 0;
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function () use (&$callCount) {
                ++$callCount;

                throw new \RuntimeException('Network error');
            });

        $service = $this->buildService($httpClient, webhookUrl: 'https://hooks.slack.com/webhook');
        $service->sendSlackNotification($run);

        $this->assertSame(3, $callCount);
    }

    public function testEmailNotificationDoesNotThrowOnFailure(): void
    {
        $run = $this->createCompletedTestRun();

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new \RuntimeException('SMTP error'));

        $service = $this->buildService(mailer: $mailer);
        $service->sendEmailNotification($run, ['test@example.com']);

        // Should not throw
        $this->addToAssertionCount(1);
    }

    // =====================
    // Allure Report URL
    // =====================

    public function testSlackMessageIncludesAllureLink(): void
    {
        $run = $this->createCompletedTestRun();

        $capturedPayload = null;
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedPayload, $response) {
                $capturedPayload = $options['json'];

                return $response;
            });

        $service = $this->buildService(
            $httpClient,
            webhookUrl: 'https://hooks.slack.com/webhook',
            allurePublicUrl: 'https://allure.example.com',
        );
        $service->sendSlackNotification($run);

        $fields = $capturedPayload['attachments'][0]['fields'];
        $reportField = array_filter($fields, fn ($f) => 'Report' === $f['title']);
        $this->assertNotEmpty($reportField);
    }

    // =====================
    // Helpers
    // =====================

    private function createCompletedTestRun(int $passedCount = 1, int $failedCount = 0): TestRun
    {
        $env = new TestEnvironment();
        $env->setName('Notif Test Env ' . uniqid());
        $env->setCode('notif-' . uniqid());
        $env->setRegion('us-east-1');
        $env->setBaseUrl('https://test.example.com');
        $env->setIsActive(true);
        $this->entityManager->persist($env);

        $run = new TestRun();
        $run->setEnvironment($env);
        $run->setType(TestRun::TYPE_MFTF);
        $run->setTriggeredBy(TestRun::TRIGGER_MANUAL);
        $run->markExecutionStarted();
        $run->markCompleted();
        $this->entityManager->persist($run);

        for ($i = 0; $i < $passedCount; ++$i) {
            $result = new TestResult();
            $result->setTestRun($run);
            $result->setTestName('PassedTest' . $i);
            $result->setStatus(TestResult::STATUS_PASSED);
            $run->addResult($result);
            $this->entityManager->persist($result);
        }

        for ($i = 0; $i < $failedCount; ++$i) {
            $result = new TestResult();
            $result->setTestRun($run);
            $result->setTestName('FailedTest' . $i);
            $result->setStatus(TestResult::STATUS_FAILED);
            $run->addResult($result);
            $this->entityManager->persist($result);
        }

        $this->entityManager->flush();

        return $run;
    }

    private function createFailedTestRun(): TestRun
    {
        $env = new TestEnvironment();
        $env->setName('Failed Notif Env ' . uniqid());
        $env->setCode('fnotif-' . uniqid());
        $env->setRegion('us-east-1');
        $env->setBaseUrl('https://test.example.com');
        $env->setIsActive(true);
        $this->entityManager->persist($env);

        $run = new TestRun();
        $run->setEnvironment($env);
        $run->setType(TestRun::TYPE_MFTF);
        $run->setTriggeredBy(TestRun::TRIGGER_MANUAL);
        $run->markFailed('Execution error');
        $this->entityManager->persist($run);
        $this->entityManager->flush();

        return $run;
    }

    private function buildService(
        ?HttpClientInterface $httpClient = null,
        ?MailerInterface $mailer = null,
        string $webhookUrl = '',
        string $allurePublicUrl = '',
    ): NotificationService {
        return new NotificationService(
            $httpClient ?? $this->createMock(HttpClientInterface::class),
            $mailer ?? $this->createMock(MailerInterface::class),
            new NullLogger(),
            $webhookUrl,
            'noreply@matre.test',
            $allurePublicUrl,
        );
    }
}
