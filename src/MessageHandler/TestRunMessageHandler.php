<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\TestRun;
use App\Entity\User;
use App\Message\TestRunMessage;
use App\Repository\TestRunRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use App\Service\TestRunnerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class TestRunMessageHandler
{
    public function __construct(
        private readonly TestRunRepository $testRunRepository,
        private readonly UserRepository $userRepository,
        private readonly TestRunnerService $testRunnerService,
        private readonly NotificationService $notificationService,
        private readonly MessageBusInterface $messageBus,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(TestRunMessage $message): void
    {
        $runId = $message->testRunId;
        $phase = $message->phase;

        $this->logger->info('Processing test run message', [
            'runId' => $runId,
            'phase' => $phase,
        ]);

        $run = $this->testRunRepository->find($runId);
        if (!$run) {
            $this->logger->error('Test run not found', ['runId' => $runId]);

            return;
        }

        // Skip only early phases for failed/cancelled runs (REPORT must run to dispatch NOTIFY)
        $skipForFailedStatuses = [TestRun::STATUS_CANCELLED, TestRun::STATUS_FAILED];
        $phasesToSkipWhenFailed = [
            TestRunMessage::PHASE_PREPARE,
            TestRunMessage::PHASE_EXECUTE,
        ];

        if (in_array($run->getStatus(), $skipForFailedStatuses, true)
            && in_array($phase, $phasesToSkipWhenFailed, true)) {
            $this->logger->info('Skipping phase for cancelled/failed run, jumping to REPORT', [
                'runId' => $runId,
                'phase' => $phase,
            ]);

            // Dispatch REPORT to continue pipeline toward NOTIFY (for failure notification)
            $this->messageBus->dispatch(new TestRunMessage(
                $runId,
                $run->getEnvironment()->getId(),
                TestRunMessage::PHASE_REPORT,
            ));

            return;
        }

        // Acquire lock to prevent concurrent execution
        $lock = $this->lockFactory->createLock('test_run_' . $runId, 3600);
        if (!$lock->acquire()) {
            $this->logger->warning('Could not acquire lock for test run', ['runId' => $runId]);

            return;
        }

        try {
            match ($phase) {
                TestRunMessage::PHASE_PREPARE => $this->handlePrepare($run),
                TestRunMessage::PHASE_EXECUTE => $this->handleExecute($run),
                TestRunMessage::PHASE_REPORT => $this->handleReport($run),
                TestRunMessage::PHASE_NOTIFY => $this->handleNotify($run),
                TestRunMessage::PHASE_CLEANUP => $this->handleCleanup($run),
                default => throw new \InvalidArgumentException('Unknown phase: ' . $phase),
            };
        } catch (\Throwable $e) {
            $this->logger->error('Test run phase failed', [
                'runId' => $runId,
                'phase' => $phase,
                'error' => $e->getMessage(),
            ]);
            // Error handling is done in the service methods
        } finally {
            $lock->release();
        }
    }

    private function handlePrepare(TestRun $run): void
    {
        $nextPhase = TestRunMessage::PHASE_EXECUTE;

        try {
            $this->testRunnerService->prepareRun($run);
        } catch (\Throwable $e) {
            $this->logger->error('Prepare phase failed, skipping to REPORT', [
                'runId' => $run->getId(),
                'error' => $e->getMessage(),
            ]);
            // Skip EXECUTE, go straight to REPORT (which will dispatch NOTIFY)
            $nextPhase = TestRunMessage::PHASE_REPORT;
        }

        $this->messageBus->dispatch(new TestRunMessage(
            $run->getId(),
            $run->getEnvironment()->getId(),
            $nextPhase,
        ));
    }

    private function handleExecute(TestRun $run): void
    {
        // Note: Lock refresh callback removed - envelope access not supported with AsMessageHandler attribute
        // For long-running tests, the per-environment lock ttl should be sufficient (30 min default)
        try {
            $this->testRunnerService->executeRun($run, null);
        } catch (\Throwable $e) {
            $this->logger->error('Execute phase failed, continuing to REPORT', [
                'runId' => $run->getId(),
                'error' => $e->getMessage(),
            ]);
            // Continue to REPORT regardless - service should have marked run as failed
        }

        // Always dispatch REPORT (handles both success and failure)
        $this->messageBus->dispatch(new TestRunMessage(
            $run->getId(),
            $run->getEnvironment()->getId(),
            TestRunMessage::PHASE_REPORT,
        ));
    }

    private function handleReport(TestRun $run): void
    {
        // Generate reports for all runs except cancelled (we need reports to see what failed!)
        if (TestRun::STATUS_CANCELLED !== $run->getStatus()) {
            try {
                $this->testRunnerService->generateReports($run);
            } catch (\Throwable $e) {
                $this->logger->error('Report generation failed, continuing to NOTIFY', [
                    'runId' => $run->getId(),
                    'error' => $e->getMessage(),
                ]);
                // Continue to NOTIFY regardless
            }
        } else {
            $this->logger->info('Skipping report generation for cancelled run', ['id' => $run->getId()]);
        }

        // Always dispatch NOTIFY (even for failed runs - they need failure notifications!)
        $this->messageBus->dispatch(new TestRunMessage(
            $run->getId(),
            $run->getEnvironment()->getId(),
            TestRunMessage::PHASE_NOTIFY,
        ));
    }

    private function handleNotify(TestRun $run): void
    {
        try {
            // Slack: send if ANY subscribed user has Slack enabled
            if ($this->userRepository->shouldSendSlackNotification($run)) {
                $this->notificationService->sendSlackNotification($run);
            }

            // Email: individual emails to each subscribed user
            $usersToEmail = $this->userRepository->findUsersToNotifyByEmail($run);
            $recipients = array_map(static fn (User $u) => $u->getEmail(), $usersToEmail);

            if (!empty($recipients)) {
                $this->notificationService->sendEmailNotification($run, $recipients);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Notification phase failed, continuing to CLEANUP', [
                'runId' => $run->getId(),
                'error' => $e->getMessage(),
            ]);
            // Continue to CLEANUP regardless
        }

        // Always dispatch CLEANUP
        $this->messageBus->dispatch(new TestRunMessage(
            $run->getId(),
            $run->getEnvironment()->getId(),
            TestRunMessage::PHASE_CLEANUP,
        ));
    }

    private function handleCleanup(TestRun $run): void
    {
        try {
            $this->testRunnerService->cleanupRun($run);
        } catch (\Throwable $e) {
            $this->logger->error('Cleanup phase failed', [
                'runId' => $run->getId(),
                'error' => $e->getMessage(),
            ]);
            // No next phase - just log and continue
        }
    }
}
