<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TestRun;
use App\Entity\User;
use App\Message\TestRunMessage;
use App\Repository\TestEnvironmentRepository;
use App\Repository\TestResultRepository;
use App\Repository\TestRunRepository;
use App\Repository\TestSuiteRepository;
use App\Repository\UserRepository;
use App\Service\AllureStepParserService;
use App\Service\ArtifactCollectorService;
use App\Service\NotificationService;
use App\Service\TestRunnerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/test-runs')]
#[IsGranted('ROLE_USER')]
class TestRunApiController extends AbstractController
{
    public function __construct(
        private readonly TestRunRepository $testRunRepository,
        private readonly TestResultRepository $testResultRepository,
        private readonly TestEnvironmentRepository $testEnvironmentRepository,
        private readonly TestSuiteRepository $testSuiteRepository,
        private readonly UserRepository $userRepository,
        private readonly TestRunnerService $testRunnerService,
        private readonly NotificationService $notificationService,
        private readonly AllureStepParserService $allureStepParser,
        private readonly ArtifactCollectorService $artifactCollector,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route('', name: 'api_test_runs_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $status = $request->query->get('status');
        $type = $request->query->get('type');
        $environmentId = $request->query->getInt('environment');
        $suiteId = $request->query->getInt('suite');

        $criteria = [];
        if ($status) {
            $criteria['status'] = $status;
        }
        if ($type) {
            $criteria['type'] = $type;
        }
        if ($environmentId) {
            $criteria['environment'] = $environmentId;
        }
        if ($suiteId) {
            $criteria['suite'] = $suiteId;
        }

        // Use eager loading to prevent N+1 on environment/suite
        $runs = $this->testRunRepository->findPaginatedWithRelations(
            $criteria,
            $limit,
            ($page - 1) * $limit,
        );

        $total = $this->testRunRepository->count($criteria);

        // Batch fetch result counts to prevent N+1 on results collection
        $runIds = array_map(fn (TestRun $run) => $run->getId(), $runs);
        $resultCounts = $this->testResultRepository->getResultCountsForRuns($runIds);

        $data = array_map(
            fn (TestRun $run) => $this->serializeRun($run, false, $resultCounts[$run->getId()] ?? null),
            $runs,
        );

        return $this->json([
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit),
            ],
        ]);
    }

    #[Route('', name: 'api_test_runs_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $errors = [];

        $environment = null;
        if (empty($data['environmentId'])) {
            $errors['environmentId'] = 'Environment is required';
        } else {
            $environment = $this->testEnvironmentRepository->find((int) $data['environmentId']);
            if (!$environment || !$environment->getIsActive()) {
                $errors['environmentId'] = 'Environment not found or inactive';
            }
        }

        $type = $data['type'] ?? null;
        if (empty($type)) {
            $errors['type'] = 'Type is required';
        } elseif (!\array_key_exists($type, TestRun::TYPES)) {
            $errors['type'] = 'Invalid type';
        }

        $suite = null;
        if (!empty($data['suiteId'])) {
            $suite = $this->testSuiteRepository->find((int) $data['suiteId']);
            if (!$suite || !$suite->isActive()) {
                $errors['suiteId'] = 'Test suite not found or inactive';
            }
        }

        $testFilter = isset($data['testFilter']) && \is_string($data['testFilter']) && '' !== trim($data['testFilter'])
            ? trim($data['testFilter'])
            : null;

        if (!$suite && null === $testFilter && !isset($errors['suiteId'])) {
            $errors['testFilter'] = 'Either suiteId or testFilter is required';
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 400);
        }

        $run = $this->testRunnerService->createRun(
            $environment,
            $type,
            $testFilter ?? $suite?->getTestPattern(),
            $suite,
            TestRun::TRIGGER_MANUAL,
            filter_var($data['sendNotifications'] ?? true, FILTER_VALIDATE_BOOLEAN),
            $this->getUser(),
        );

        $this->messageBus->dispatch(new TestRunMessage(
            $run->getId(),
            $run->getEnvironment()->getId(),
            TestRunMessage::PHASE_PREPARE,
        ));

        return $this->json([
            'success' => true,
            'id' => $run->getId(),
            'run' => $this->serializeRun($run),
        ], 201);
    }

    #[Route('/{id}', name: 'api_test_runs_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(TestRun $run): JsonResponse
    {
        return $this->json($this->serializeRun($run, true));
    }

    #[Route('/{id}/cancel', name: 'api_test_runs_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function cancel(TestRun $run): JsonResponse
    {
        if (!$run->canBeCancelled()) {
            return $this->json(['error' => 'Run cannot be cancelled'], 400);
        }

        $this->testRunnerService->cancelRun($run);

        return $this->json(['message' => 'Run cancelled', 'run' => $this->serializeRun($run)]);
    }

    #[Route('/{id}/retry', name: 'api_test_runs_retry', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function retry(TestRun $run): JsonResponse
    {
        $newRun = $this->testRunnerService->retryRun($run, $this->getUser());

        $this->messageBus->dispatch(new TestRunMessage(
            $newRun->getId(),
            $newRun->getEnvironment()->getId(),
            TestRunMessage::PHASE_PREPARE,
        ));

        return $this->json([
            'message' => 'New run created',
            'run' => $this->serializeRun($newRun),
        ]);
    }

    #[Route('/{id}/retry-failed', name: 'api_test_runs_retry_failed', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function retryFailed(TestRun $run): JsonResponse
    {
        $newRun = $this->testRunnerService->retryFailedRun($run, $this->getUser());

        if (null === $newRun) {
            return $this->json(['error' => 'No retryable failures found. Only WebDriver/infrastructure errors can be retried.'], 400);
        }

        $this->messageBus->dispatch(new TestRunMessage(
            $newRun->getId(),
            $newRun->getEnvironment()->getId(),
            TestRunMessage::PHASE_PREPARE,
        ));

        return $this->json([
            'message' => sprintf('Retry run created for %d failed test(s)', \count($newRun->getRetryTestIdsArray())),
            'run' => $this->serializeRun($newRun),
        ]);
    }

    #[Route('/{id}/resend-notification', name: 'api_test_runs_resend_notification', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function resendNotification(TestRun $run): JsonResponse
    {
        if (!$run->isFinished()) {
            return $this->json(['error' => 'Can only resend notifications for finished runs'], 400);
        }

        $slackSent = false;
        $emailSent = false;

        if ($this->userRepository->shouldSendSlackNotification($run)) {
            $this->notificationService->sendSlackNotification($run);
            $slackSent = true;
        }

        $usersToEmail = $this->userRepository->findUsersToNotifyByEmail($run);
        $recipients = array_map(static fn (User $u) => $u->getEmail(), $usersToEmail);
        if (!empty($recipients)) {
            $this->notificationService->sendEmailNotification($run, $recipients);
            $emailSent = true;
        }

        if (!$slackSent && !$emailSent) {
            return $this->json(['error' => 'No users subscribed to notifications for this environment'], 400);
        }

        $channels = array_filter(['Slack' => $slackSent, 'Email' => $emailSent], fn ($v) => $v);

        return $this->json(['message' => 'Notification sent via: ' . implode(', ', array_keys($channels))]);
    }

    /**
     * Get live output for a running test.
     */
    #[Route('/{id}/live-output', name: 'admin_test_run_live_output', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function liveOutput(int $id): JsonResponse
    {
        $run = $this->testRunRepository->find($id);
        if (!$run) {
            return new JsonResponse([
                'error' => sprintf('Test run #%d does not exist.', $id),
            ], 404);
        }

        // For sequential group runs, return current test output
        $currentTest = $run->getCurrentTestName();
        $output = '';

        if (null !== $currentTest) {
            // Find current test's output file
            $safeFileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $currentTest);
            $outputPath = $this->getParameter('kernel.project_dir')
                . sprintf('/var/test-output/run-%d/%s.log', $run->getId(), $safeFileName);

            if (file_exists($outputPath)) {
                $output = $this->readTailOfFile($outputPath, 102400);
            }
        } else {
            // Fallback to existing behavior for non-group runs
            $outputPath = $run->getOutputFilePath();
            if ($outputPath && file_exists($outputPath)) {
                $output = $this->readTailOfFile($outputPath, 102400);
            }
        }

        // Build progress string - show current test number (running), not just completed
        $progress = null;
        if (null !== $run->getTotalTests()) {
            $completed = $run->getCompletedTests() ?? 0;
            $total = $run->getTotalTests();
            // If a test is currently running, show that test number (completed + 1)
            $current = null !== $currentTest ? $completed + 1 : $completed;
            $progress = sprintf('%d/%d', $current, $total);
        }

        // Build results array for live display
        $results = [];
        foreach ($run->getResults() as $result) {
            $results[] = [
                'id' => $result->getId(),
                'testName' => $result->getTestName(),
                'status' => $result->getStatus(),
                'duration' => $result->getDuration(),
                'durationFormatted' => $result->getDurationFormatted(),
                'errorMessage' => $result->getErrorMessage(),
                'hasScreenshot' => null !== $result->getScreenshotPath(),
                'hasOutputFile' => null !== $result->getOutputFilePath(),
            ];
        }

        return new JsonResponse([
            'status' => $run->getStatus(),
            'currentTest' => $currentTest,
            'progress' => $progress,
            'output' => $output,
            'resultCounts' => $run->getResultCounts(),
            'results' => $results,
            'isFinished' => $run->isFinished(),
            'isWatchable' => $this->isWatchable($run),
        ]);
    }

    /**
     * Get Allure execution steps for a specific test result.
     */
    #[Route('/{id}/results/{resultId}/steps', name: 'admin_test_run_result_steps', methods: ['GET'], requirements: ['id' => '\d+', 'resultId' => '\d+'])]
    public function getResultSteps(int $id, int $resultId): JsonResponse
    {
        $run = $this->testRunRepository->find($id);
        if (!$run) {
            return new JsonResponse(['error' => 'Test run not found'], 404);
        }

        $result = $this->testResultRepository->find($resultId);
        if (!$result || $result->getTestRun()->getId() !== $run->getId()) {
            return new JsonResponse(['error' => 'Test result not found'], 404);
        }

        $steps = $this->allureStepParser->getStepsForResult($result);

        if (!$steps) {
            return new JsonResponse([
                'testName' => $result->getTestName(),
                'status' => $result->getStatus(),
                'duration' => $result->getDuration(),
                'steps' => [],
                'error' => 'Step details unavailable (Allure data not found)',
            ]);
        }

        // Backfill duration from Allure if missing in DB
        if (null === $result->getDuration() && isset($steps['duration']) && null !== $steps['duration']) {
            $result->setDuration($steps['duration']);
            $this->entityManager->flush();
        }

        return new JsonResponse($steps);
    }

    /**
     * Get individual test output for sequential group runs.
     */
    #[Route('/{id}/results/{resultId}/output', name: 'admin_test_run_result_output', methods: ['GET'], requirements: ['id' => '\d+', 'resultId' => '\d+'])]
    public function getTestOutput(TestRun $run, int $resultId): JsonResponse
    {
        $result = $this->testResultRepository->find($resultId);
        if (!$result || $result->getTestRun()->getId() !== $run->getId()) {
            throw $this->createNotFoundException('Test result not found');
        }

        $outputPath = $result->getOutputFilePath();
        if (!$outputPath || !file_exists($outputPath)) {
            return $this->json(['output' => 'Output file not available']);
        }

        $output = $this->readTailOfFile($outputPath, 1024 * 1024); // 1MB limit

        return $this->json([
            'testName' => $result->getTestName(),
            'status' => $result->getStatus(),
            'output' => $output,
        ]);
    }

    /**
     * Serialize a test run to array.
     *
     * @param array{passed: int, failed: int, skipped: int, broken: int, total: int}|null $resultCounts Pre-fetched result counts (prevents N+1)
     */
    private function serializeRun(TestRun $run, bool $includeDetails = false, ?array $resultCounts = null): array
    {
        $data = [
            'id' => $run->getId(),
            'status' => $run->getStatus(),
            'type' => $run->getType(),
            'testFilter' => $run->getTestFilter(),
            'triggeredBy' => $run->getTriggeredBy(),
            'createdAt' => $run->getCreatedAt()->format('c'),
            'startedAt' => $run->getStartedAt()?->format('c'),
            'completedAt' => $run->getCompletedAt()?->format('c'),
            'duration' => $run->getDurationFormatted(),
            'environment' => [
                'id' => $run->getEnvironment()->getId(),
                'name' => $run->getEnvironment()->getName(),
                'code' => $run->getEnvironment()->getCode(),
                'region' => $run->getEnvironment()->getRegion(),
            ],
            'suite' => $run->getSuite() ? [
                'id' => $run->getSuite()->getId(),
                'name' => $run->getSuite()->getName(),
            ] : null,
            'executedBy' => $run->getExecutedBy() ? [
                'id' => $run->getExecutedBy()->getId(),
                'username' => $run->getExecutedBy()->getUsername(),
            ] : null,
            // Use pre-fetched counts if provided, otherwise fall back to entity method
            'resultCounts' => $resultCounts ?? $run->getResultCounts(),
            'canBeCancelled' => $run->canBeCancelled(),
            'isFinished' => $run->isFinished(),
            'isWatchable' => $this->isWatchable($run),
        ];

        if ($includeDetails) {
            $data['output'] = $run->getOutput();
            $data['errorMessage'] = $run->getErrorMessage();
            $data['totalTests'] = $run->getTotalTests();
            $data['completedTests'] = $run->getCompletedTests();
            $data['currentTestName'] = $run->getCurrentTestName();
            $data['retryAttempt'] = $run->getRetryAttempt();
            $data['retryOfFailed'] = $run->isRetryOfFailed();
            $data['originalRun'] = $run->getOriginalRun() ? ['id' => $run->getOriginalRun()->getId()] : null;
            $data['results'] = array_map(fn ($result) => [
                'id' => $result->getId(),
                'testName' => $result->getTestName(),
                'testId' => $result->getTestId(),
                'status' => $result->getStatus(),
                'duration' => $result->getDuration(),
                'durationFormatted' => $result->getDurationFormatted(),
                'errorMessage' => $result->getErrorMessage(),
                'screenshotPath' => $result->getScreenshotPath(),
                'hasOutput' => null !== $result->getOutputFilePath(),
            ], $run->getResults()->toArray());
            $data['artifacts'] = $this->artifactCollector->groupArtifactsForDisplay(
                $this->artifactCollector->listArtifacts($run),
            );
            $data['reports'] = array_map(fn ($report) => [
                'id' => $report->getId(),
                'type' => $report->getReportType(),
                'publicUrl' => $report->getPublicUrl(),
                'generatedAt' => $report->getGeneratedAt()->format('c'),
            ], $run->getReports()->toArray());
        }

        return $data;
    }

    private function isWatchable(TestRun $run): bool
    {
        return \in_array($run->getStatus(), [TestRun::STATUS_PREPARING, TestRun::STATUS_CLONING, TestRun::STATUS_RUNNING], true);
    }

    /**
     * Read tail of file to prevent memory issues with large logs.
     */
    private function readTailOfFile(string $path, int $maxBytes): string
    {
        $size = filesize($path);
        if ($size <= $maxBytes) {
            return file_get_contents($path);
        }

        $handle = fopen($path, 'r');
        fseek($handle, -$maxBytes, SEEK_END);
        $content = fread($handle, $maxBytes);
        fclose($handle);

        return '... [truncated - showing last ' . round($maxBytes / 1024) . "KB]\n" . $content;
    }
}
