<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\NotificationTemplate;
use App\Entity\Settings;
use App\Entity\TestEnvironment;
use App\Entity\TestRun;
use App\Repository\NotificationTemplateRepository;
use App\Repository\SettingsRepository;
use App\Service\NotificationTemplateService;
use PHPUnit\Framework\TestCase;

class NotificationTemplateServiceTest extends TestCase
{
    // --- render ---

    public function testRenderReturnsNullsWhenNoTemplateFound(): void
    {
        $repo = $this->createStub(NotificationTemplateRepository::class);
        $repo->method('findActiveByChannelAndName')->willReturn(null);

        $service = $this->createService(repository: $repo);
        $result = $service->render($this->createTestRun(), NotificationTemplate::CHANNEL_SLACK, NotificationTemplate::NAME_COMPLETED_SUCCESS);

        self::assertNull($result['subject']);
        self::assertNull($result['body']);
    }

    public function testRenderReplacesVariablesInSubjectAndBody(): void
    {
        $template = $this->createTemplate(
            'Run #{{ run_id }} - {{ run_status }}',
            'Environment: {{ environment_name }}, Type: {{ test_type }}',
        );

        $repo = $this->createStub(NotificationTemplateRepository::class);
        $repo->method('findActiveByChannelAndName')->willReturn($template);

        $service = $this->createService(repository: $repo);
        $result = $service->render($this->createTestRun(), NotificationTemplate::CHANNEL_EMAIL, NotificationTemplate::NAME_COMPLETED_FAILURES);

        self::assertSame('Run #123 - Completed', $result['subject']);
        self::assertSame('Environment: Staging, Type: MFTF', $result['body']);
    }

    public function testRenderReturnsNullSubjectWhenTemplateHasNoSubject(): void
    {
        $template = $this->createTemplate(null, 'Body text {{ run_id }}');

        $repo = $this->createStub(NotificationTemplateRepository::class);
        $repo->method('findActiveByChannelAndName')->willReturn($template);

        $service = $this->createService(repository: $repo);
        $result = $service->render($this->createTestRun(), NotificationTemplate::CHANNEL_SLACK, NotificationTemplate::NAME_COMPLETED_SUCCESS);

        self::assertNull($result['subject']);
        self::assertSame('Body text 123', $result['body']);
    }

    public function testRenderProcessesConditionalTrue(): void
    {
        $template = $this->createTemplate(null, '{% if has_failures %}FAILURES{% endif %}');

        $repo = $this->createStub(NotificationTemplateRepository::class);
        $repo->method('findActiveByChannelAndName')->willReturn($template);

        $service = $this->createService(repository: $repo);
        $result = $service->render(
            $this->createTestRun(resultCounts: ['total' => 5, 'passed' => 3, 'failed' => 2, 'broken' => 0, 'skipped' => 0]),
            NotificationTemplate::CHANNEL_SLACK,
            NotificationTemplate::NAME_COMPLETED_FAILURES,
        );

        self::assertSame('FAILURES', $result['body']);
    }

    public function testRenderProcessesConditionalFalse(): void
    {
        $template = $this->createTemplate(null, '{% if has_failures %}FAILURES{% endif %}');

        $repo = $this->createStub(NotificationTemplateRepository::class);
        $repo->method('findActiveByChannelAndName')->willReturn($template);

        $service = $this->createService(repository: $repo);
        $result = $service->render(
            $this->createTestRun(resultCounts: ['total' => 5, 'passed' => 5, 'failed' => 0, 'broken' => 0, 'skipped' => 0]),
            NotificationTemplate::CHANNEL_SLACK,
            NotificationTemplate::NAME_COMPLETED_SUCCESS,
        );

        self::assertSame('', $result['body']);
    }

    public function testRenderProcessesComparisonCondition(): void
    {
        $template = $this->createTemplate(null, '{% if total_count > 0 %}HAS RESULTS{% endif %}');

        $repo = $this->createStub(NotificationTemplateRepository::class);
        $repo->method('findActiveByChannelAndName')->willReturn($template);

        $service = $this->createService(repository: $repo);
        $result = $service->render($this->createTestRun(), NotificationTemplate::CHANNEL_SLACK, NotificationTemplate::NAME_COMPLETED_SUCCESS);

        self::assertSame('HAS RESULTS', $result['body']);
    }

    public function testRenderProcessesHasFilterConditional(): void
    {
        $template = $this->createTemplate(null, '{% if has_filter %}Filter: {{ test_filter }}{% endif %}');

        $repo = $this->createStub(NotificationTemplateRepository::class);
        $repo->method('findActiveByChannelAndName')->willReturn($template);

        $service = $this->createService(repository: $repo);

        $withFilter = $service->render(
            $this->createTestRun(testFilter: '@smoke'),
            NotificationTemplate::CHANNEL_SLACK,
            NotificationTemplate::NAME_COMPLETED_SUCCESS,
        );
        self::assertSame('Filter: @smoke', $withFilter['body']);

        $withoutFilter = $service->render(
            $this->createTestRun(testFilter: null),
            NotificationTemplate::CHANNEL_SLACK,
            NotificationTemplate::NAME_COMPLETED_SUCCESS,
        );
        self::assertSame('', $withoutFilter['body']);
    }

    public function testRenderProcessesHasErrorConditional(): void
    {
        $template = $this->createTemplate(null, '{% if has_error %}Error: {{ error_message }}{% endif %}');

        $repo = $this->createStub(NotificationTemplateRepository::class);
        $repo->method('findActiveByChannelAndName')->willReturn($template);

        $service = $this->createService(repository: $repo);

        $withError = $service->render(
            $this->createTestRun(status: TestRun::STATUS_FAILED, errorMessage: 'Docker timeout'),
            NotificationTemplate::CHANNEL_SLACK,
            NotificationTemplate::NAME_FAILED,
        );
        self::assertSame('Error: Docker timeout', $withError['body']);

        $withoutError = $service->render(
            $this->createTestRun(),
            NotificationTemplate::CHANNEL_SLACK,
            NotificationTemplate::NAME_COMPLETED_SUCCESS,
        );
        self::assertSame('', $withoutError['body']);
    }

    // --- renderPreview ---

    public function testRenderPreviewReplacesWithSampleData(): void
    {
        $service = $this->createService();
        $result = $service->renderPreview(
            'Run #{{ run_id }}',
            'Env: {{ environment_name }}, Status: {{ run_status }}',
            NotificationTemplate::CHANNEL_EMAIL,
        );

        self::assertSame('Run #123', $result['subject']);
        self::assertSame('Env: Stage US, Status: Completed', $result['body']);
    }

    public function testRenderPreviewReturnsNullSubjectWhenEmpty(): void
    {
        $service = $this->createService();
        $result = $service->renderPreview('', 'body {{ run_id }}', NotificationTemplate::CHANNEL_SLACK);

        self::assertNull($result['subject']);
        self::assertSame('body 123', $result['body']);
    }

    // --- buildVariables ---

    public function testBuildVariablesIncludesAllExpectedKeys(): void
    {
        $service = $this->createService();
        $vars = $service->buildVariables($this->createTestRun());

        self::assertSame('123', $vars['run_id']);
        self::assertSame('Completed', $vars['run_status']);
        self::assertSame('Staging', $vars['environment_name']);
        self::assertSame('staging', $vars['environment_code']);
        self::assertSame('MFTF', $vars['test_type']);
        self::assertSame('5m 30s', $vars['duration']);
        self::assertSame('Manual', $vars['triggered_by']);
        self::assertSame('8', $vars['passed_count']);
        self::assertSame('2', $vars['failed_count']);
        self::assertSame('0', $vars['broken_count']);
        self::assertSame('0', $vars['skipped_count']);
        self::assertSame('10', $vars['total_count']);
        self::assertSame('MATRE', $vars['site_name']);
    }

    public function testBuildVariablesConditionalFlags(): void
    {
        $service = $this->createService();

        $withFailures = $service->buildVariables($this->createTestRun());
        self::assertTrue($withFailures['has_failures']);
        self::assertFalse($withFailures['has_filter']);
        self::assertFalse($withFailures['has_error']);

        $withExtras = $service->buildVariables($this->createTestRun(testFilter: '@smoke', errorMessage: 'err'));
        self::assertTrue($withExtras['has_filter']);
        self::assertTrue($withExtras['has_error']);
    }

    public function testBuildVariablesHasNoFailuresWhenAllPassed(): void
    {
        $service = $this->createService();
        $vars = $service->buildVariables($this->createTestRun(
            resultCounts: ['total' => 5, 'passed' => 5, 'failed' => 0, 'broken' => 0, 'skipped' => 0],
        ));

        self::assertFalse($vars['has_failures']);
    }

    public function testBuildVariablesAllureReportUrl(): void
    {
        $service = $this->createService(allurePublicUrl: 'https://allure.example.com');
        $vars = $service->buildVariables($this->createTestRun());

        self::assertSame(
            'https://allure.example.com/allure-docker-service/projects/staging/reports/latest/index.html',
            $vars['allure_report_url'],
        );
    }

    public function testBuildVariablesAllureReportUrlEmptyWhenNoPublicUrl(): void
    {
        $service = $this->createService(allurePublicUrl: '');
        $vars = $service->buildVariables($this->createTestRun());

        self::assertSame('', $vars['allure_report_url']);
    }

    public function testBuildVariablesStatusEmojiAndColor(): void
    {
        $service = $this->createService();

        $completedWithFailures = $service->buildVariables($this->createTestRun());
        self::assertSame(':warning:', $completedWithFailures['status_emoji']);
        self::assertSame('warning', $completedWithFailures['status_color']);

        $completedSuccess = $service->buildVariables($this->createTestRun(
            resultCounts: ['total' => 5, 'passed' => 5, 'failed' => 0, 'broken' => 0, 'skipped' => 0],
        ));
        self::assertSame(':white_check_mark:', $completedSuccess['status_emoji']);
        self::assertSame('good', $completedSuccess['status_color']);

        $failed = $service->buildVariables($this->createTestRun(status: TestRun::STATUS_FAILED));
        self::assertSame(':x:', $failed['status_emoji']);
        self::assertSame('danger', $failed['status_color']);

        $cancelled = $service->buildVariables($this->createTestRun(status: TestRun::STATUS_CANCELLED));
        self::assertSame(':no_entry:', $cancelled['status_emoji']);
        self::assertSame('#808080', $cancelled['status_color']);
    }

    // --- getEventName ---

    public function testGetEventNameCompletedSuccess(): void
    {
        $service = $this->createService();
        $run = $this->createTestRun(
            status: TestRun::STATUS_COMPLETED,
            resultCounts: ['total' => 5, 'passed' => 5, 'failed' => 0, 'broken' => 0, 'skipped' => 0],
        );

        self::assertSame(NotificationTemplate::NAME_COMPLETED_SUCCESS, $service->getEventName($run));
    }

    public function testGetEventNameCompletedFailures(): void
    {
        $service = $this->createService();
        $run = $this->createTestRun(
            status: TestRun::STATUS_COMPLETED,
            resultCounts: ['total' => 10, 'passed' => 8, 'failed' => 2, 'broken' => 0, 'skipped' => 0],
        );

        self::assertSame(NotificationTemplate::NAME_COMPLETED_FAILURES, $service->getEventName($run));
    }

    public function testGetEventNameFailed(): void
    {
        $service = $this->createService();
        self::assertSame(NotificationTemplate::NAME_FAILED, $service->getEventName($this->createTestRun(status: TestRun::STATUS_FAILED)));
    }

    public function testGetEventNameCancelled(): void
    {
        $service = $this->createService();
        self::assertSame(NotificationTemplate::NAME_CANCELLED, $service->getEventName($this->createTestRun(status: TestRun::STATUS_CANCELLED)));
    }

    public function testGetEventNameDefaultsToSuccess(): void
    {
        $service = $this->createService();
        $run = $this->createTestRun(status: TestRun::STATUS_RUNNING);

        self::assertSame(NotificationTemplate::NAME_COMPLETED_SUCCESS, $service->getEventName($run));
    }

    // --- getAvailableVariables ---

    public function testGetAvailableVariablesReturnsNonEmptyList(): void
    {
        $service = $this->createService();
        $vars = $service->getAvailableVariables();

        self::assertNotEmpty($vars);
        self::assertArrayHasKey('name', $vars[0]);
        self::assertArrayHasKey('description', $vars[0]);
    }

    public function testGetAvailableVariablesContainsRunId(): void
    {
        $service = $this->createService();
        $names = array_column($service->getAvailableVariables(), 'name');

        self::assertContains('run_id', $names);
        self::assertContains('environment_name', $names);
        self::assertContains('status_emoji', $names);
    }

    // --- getDefaultTemplateContent ---

    public function testGetDefaultTemplateContentSlackSuccess(): void
    {
        $service = $this->createService();
        $content = $service->getDefaultTemplateContent(NotificationTemplate::CHANNEL_SLACK, NotificationTemplate::NAME_COMPLETED_SUCCESS);

        self::assertNull($content['subject']);
        self::assertStringContainsString('{{ run_id }}', $content['body']);
        self::assertStringContainsString('{{ environment_name }}', $content['body']);
    }

    public function testGetDefaultTemplateContentEmailSuccess(): void
    {
        $service = $this->createService();
        $content = $service->getDefaultTemplateContent(NotificationTemplate::CHANNEL_EMAIL, NotificationTemplate::NAME_COMPLETED_SUCCESS);

        self::assertNotNull($content['subject']);
        self::assertStringContainsString('{{ run_id }}', $content['subject']);
        self::assertStringContainsString('<html>', $content['body']);
    }

    public function testGetDefaultTemplateContentUnknownReturnsEmpty(): void
    {
        $service = $this->createService();
        $content = $service->getDefaultTemplateContent('unknown_channel', 'unknown_name');

        self::assertNull($content['subject']);
        self::assertSame('', $content['body']);
    }

    public function testGetDefaultTemplateContentAllChannelsAndNames(): void
    {
        $service = $this->createService();

        foreach (NotificationTemplate::CHANNELS as $channel) {
            foreach (NotificationTemplate::NAMES as $name) {
                $content = $service->getDefaultTemplateContent($channel, $name);
                self::assertArrayHasKey('subject', $content);
                self::assertArrayHasKey('body', $content);
                self::assertNotEmpty($content['body'], "Body empty for {$channel}_{$name}");
            }
        }
    }

    private function createService(
        ?NotificationTemplateRepository $repository = null,
        ?SettingsRepository $settingsRepository = null,
        string $allurePublicUrl = 'https://allure.example.com',
    ): NotificationTemplateService {
        return new NotificationTemplateService(
            $repository ?? $this->createStub(NotificationTemplateRepository::class),
            $settingsRepository ?? $this->createSettingsRepository(),
            $allurePublicUrl,
        );
    }

    private function createSettingsRepository(): SettingsRepository
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('getSiteName')->willReturn('MATRE');

        $repo = $this->createStub(SettingsRepository::class);
        $repo->method('getSettings')->willReturn($settings);

        return $repo;
    }

    private function createTestRun(
        string $status = TestRun::STATUS_COMPLETED,
        array $resultCounts = ['total' => 10, 'passed' => 8, 'failed' => 2, 'broken' => 0, 'skipped' => 0],
        ?string $testFilter = null,
        ?string $errorMessage = null,
    ): TestRun {
        $env = $this->createStub(TestEnvironment::class);
        $env->method('getName')->willReturn('Staging');
        $env->method('getCode')->willReturn('staging');

        $run = $this->createStub(TestRun::class);
        $run->method('getId')->willReturn(123);
        $run->method('getStatus')->willReturn($status);
        $run->method('getEnvironment')->willReturn($env);
        $run->method('getType')->willReturn('mftf');
        $run->method('getDurationFormatted')->willReturn('5m 30s');
        $run->method('getTriggeredBy')->willReturn('manual');
        $run->method('getResultCounts')->willReturn($resultCounts);
        $run->method('getTestFilter')->willReturn($testFilter);
        $run->method('getErrorMessage')->willReturn($errorMessage);

        return $run;
    }

    private function createTemplate(?string $subject, string $body): NotificationTemplate
    {
        $template = $this->createStub(NotificationTemplate::class);
        $template->method('getSubject')->willReturn($subject);
        $template->method('getBody')->willReturn($body);

        return $template;
    }
}
