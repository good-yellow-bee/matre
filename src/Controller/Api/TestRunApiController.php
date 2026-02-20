<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TestRun;
use App\Message\TestRunMessage;
use App\Repository\TestResultRepository;
use App\Repository\TestRunRepository;
use App\Service\TestRunnerService;
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
        private readonly TestRunnerService $testRunnerService,
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

    #[Route('/{id}', name: 'api_test_runs_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(TestRun $run): JsonResponse
    {
        return $this->json($this->serializeRun($run, true));
    }

    #[Route('/{id}/cancel', name: 'api_test_runs_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function cancel(Request $request, TestRun $run): JsonResponse
    {
        if (!$this->isCsrfTokenValid('test_run_api', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        if (!$run->canBeCancelled()) {
            return $this->json(['error' => 'Run cannot be cancelled'], 400);
        }

        $this->testRunnerService->cancelRun($run);

        return $this->json(['message' => 'Run cancelled', 'run' => $this->serializeRun($run)]);
    }

    #[Route('/{id}/retry', name: 'api_test_runs_retry', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function retry(Request $request, TestRun $run): JsonResponse
    {
        if (!$this->isCsrfTokenValid('test_run_api', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

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
        ];

        if ($includeDetails) {
            $data['output'] = $run->getOutput();
            $data['errorMessage'] = $run->getErrorMessage();
            $data['results'] = array_map(fn ($result) => [
                'id' => $result->getId(),
                'testName' => $result->getTestName(),
                'testId' => $result->getTestId(),
                'status' => $result->getStatus(),
                'duration' => $result->getDuration(),
                'errorMessage' => $result->getErrorMessage(),
            ], $run->getResults()->toArray());
            $data['reports'] = array_map(fn ($report) => [
                'id' => $report->getId(),
                'type' => $report->getReportType(),
                'publicUrl' => $report->getPublicUrl(),
                'generatedAt' => $report->getGeneratedAt()->format('c'),
            ], $run->getReports()->toArray());
        }

        return $data;
    }
}
