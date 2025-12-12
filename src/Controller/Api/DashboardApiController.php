<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\TestEnvironmentRepository;
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
}
