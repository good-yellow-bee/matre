<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\TestRun;
use App\Message\ScheduledTestRunMessage;
use App\Message\TestRunMessage;
use App\Repository\TestEnvironmentRepository;
use App\Repository\TestSuiteRepository;
use App\Service\TestRunnerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles scheduled test suite execution.
 *
 * Creates test runs for all active environments when a suite is scheduled.
 */
#[AsMessageHandler]
class ScheduledTestRunMessageHandler
{
    public function __construct(
        private readonly TestSuiteRepository $testSuiteRepository,
        private readonly TestEnvironmentRepository $testEnvironmentRepository,
        private readonly TestRunnerService $testRunnerService,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScheduledTestRunMessage $message): void
    {
        $suite = $this->testSuiteRepository->find($message->suiteId);
        if (!$suite || !$suite->isActive()) {
            $this->logger->info('Suite not found or inactive', ['suiteId' => $message->suiteId]);
            return;
        }

        $this->logger->info('Processing scheduled test suite', [
            'suiteId' => $suite->getId(),
            'suiteName' => $suite->getName(),
        ]);

        // Get all active environments
        $environments = $this->testEnvironmentRepository->findActive();

        foreach ($environments as $environment) {
            // Skip if there's already a running test for this environment
            if ($this->testRunnerService->hasRunningForEnvironment($environment)) {
                $this->logger->info('Skipping environment with running tests', [
                    'environment' => $environment->getName(),
                ]);
                continue;
            }

            // Determine test type from suite type
            $type = match (true) {
                str_starts_with($suite->getType(), 'mftf') => TestRun::TYPE_MFTF,
                str_starts_with($suite->getType(), 'playwright') => TestRun::TYPE_PLAYWRIGHT,
                default => TestRun::TYPE_BOTH,
            };

            // Create test run
            $run = $this->testRunnerService->createRun(
                $environment,
                $type,
                $suite->getTestPattern(),
                $suite,
                TestRun::TRIGGER_SCHEDULER
            );

            $this->logger->info('Created scheduled test run', [
                'runId' => $run->getId(),
                'environment' => $environment->getName(),
            ]);

            // Dispatch execution
            $this->messageBus->dispatch(new TestRunMessage(
                $run->getId(),
                TestRunMessage::PHASE_PREPARE
            ));
        }
    }
}
