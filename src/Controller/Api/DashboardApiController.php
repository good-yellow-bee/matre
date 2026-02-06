<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\TestEnvironmentRepository;
use App\Repository\TestResultRepository;
use App\Repository\TestRunRepository;
use App\Repository\TestSuiteRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardApiController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TestRunRepository $testRunRepository,
        private readonly TestEnvironmentRepository $testEnvironmentRepository,
        private readonly TestSuiteRepository $testSuiteRepository,
        private readonly TestResultRepository $testResultRepository,
    ) {
    }

    /**
     * Get dashboard statistics.
     *
     * Returns counts and metrics for the admin dashboard
     */
    #[Route('/stats', name: 'api_dashboard_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        // User statistics
        $totalUsers = $this->userRepository->countAll();
        $activeUsers = $this->userRepository->countActive();

        // Test run statistics (last 30 days)
        $testStats = $this->testRunRepository->getStatistics(30);

        // Environment statistics
        $environments = $this->testEnvironmentRepository->findAllOrdered();
        $activeEnvironments = $this->testEnvironmentRepository->findActive();

        // Suite statistics
        $suites = $this->testSuiteRepository->findAllOrdered();
        $activeSuites = $this->testSuiteRepository->findActive();
        $scheduledSuites = $this->testSuiteRepository->findScheduled();

        // Running tests
        $runningTests = $this->testRunRepository->findRunning();

        return $this->json([
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'inactive' => $totalUsers - $activeUsers,
            ],
            'testRuns' => [
                'total' => $testStats['total'],
                'completed' => $testStats['completed'],
                'failed' => $testStats['failed'],
                'running' => $testStats['running'],
                'pending' => $testStats['pending'],
                'period' => '30 days',
            ],
            'environments' => [
                'total' => count($environments),
                'active' => count($activeEnvironments),
            ],
            'suites' => [
                'total' => count($suites),
                'active' => count($activeSuites),
                'scheduled' => count($scheduledSuites),
            ],
            'activity' => [
                'runningNow' => count($runningTests),
            ],
        ]);
    }

    #[Route('/environment-stats', name: 'api_dashboard_environment_stats', methods: ['GET'])]
    public function environmentStats(): JsonResponse
    {
        $environments = $this->testEnvironmentRepository->findActive();

        if (empty($environments)) {
            return $this->json(['environments' => []]);
        }

        $envIds = array_map(fn ($e) => $e->getId(), $environments);
        $lastRuns = $this->testRunRepository->findLastTwoCompletedPerEnvironment($envIds);

        // Collect all run IDs for batch result query
        $allRunIds = [];
        foreach ($lastRuns as $runs) {
            if ($runs['current']) {
                $allRunIds[] = $runs['current']['id'];
            }
            if ($runs['previous']) {
                $allRunIds[] = $runs['previous']['id'];
            }
        }

        $resultCounts = $this->testResultRepository->getResultCountsForRuns($allRunIds);

        $data = [];
        foreach ($environments as $env) {
            $envId = $env->getId();
            $runs = $lastRuns[$envId] ?? ['current' => null, 'previous' => null];

            $entry = [
                'id' => $envId,
                'name' => $env->getName(),
                'code' => $env->getCode(),
                'region' => $env->getRegion(),
                'lastRun' => null,
            ];

            if ($runs['current']) {
                $runId = $runs['current']['id'];
                $counts = $resultCounts[$runId] ?? ['passed' => 0, 'failed' => 0, 'skipped' => 0, 'broken' => 0, 'total' => 0];
                $passRate = $counts['total'] > 0 ? round($counts['passed'] / $counts['total'] * 100, 1) : 0;

                $passRateDelta = null;
                if ($runs['previous']) {
                    $prevId = $runs['previous']['id'];
                    $prevCounts = $resultCounts[$prevId] ?? ['passed' => 0, 'failed' => 0, 'skipped' => 0, 'broken' => 0, 'total' => 0];
                    $prevPassRate = $prevCounts['total'] > 0 ? round($prevCounts['passed'] / $prevCounts['total'] * 100, 1) : 0;
                    $passRateDelta = round($passRate - $prevPassRate, 1);
                }

                $entry['lastRun'] = [
                    'id' => $runId,
                    'status' => $runs['current']['status'],
                    'completedAt' => $runs['current']['completed_at'],
                    'results' => [
                        'passed' => $counts['passed'],
                        'failed' => $counts['failed'],
                        'skipped' => $counts['skipped'],
                        'broken' => $counts['broken'],
                        'total' => $counts['total'],
                        'passRate' => $passRate,
                    ],
                    'passRateDelta' => $passRateDelta,
                ];
            }

            $data[] = $entry;
        }

        return $this->json(['environments' => $data]);
    }
}
