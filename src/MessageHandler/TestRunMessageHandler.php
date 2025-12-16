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

        // Skip if already cancelled or failed
        if (in_array($run->getStatus(), [TestRun::STATUS_CANCELLED, TestRun::STATUS_FAILED], true)) {
            $this->logger->info('Skipping cancelled/failed run', ['runId' => $runId]);

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
        $this->testRunnerService->prepareRun($run);

        // Dispatch next phase
        $this->messageBus->dispatch(new TestRunMessage(
            $run->getId(),
            $run->getEnvironment()->getId(),
            TestRunMessage::PHASE_EXECUTE,
        ));
    }

    private function handleExecute(TestRun $run): void
    {
        $this->testRunnerService->executeRun($run);

        // Dispatch next phase
        $this->messageBus->dispatch(new TestRunMessage(
            $run->getId(),
            $run->getEnvironment()->getId(),
            TestRunMessage::PHASE_REPORT,
        ));
    }

    private function handleReport(TestRun $run): void
    {
        $this->testRunnerService->generateReports($run);

        // Dispatch next phase
        $this->messageBus->dispatch(new TestRunMessage(
            $run->getId(),
            $run->getEnvironment()->getId(),
            TestRunMessage::PHASE_NOTIFY,
        ));
    }

    private function handleNotify(TestRun $run): void
    {
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

        // Dispatch cleanup phase
        $this->messageBus->dispatch(new TestRunMessage(
            $run->getId(),
            $run->getEnvironment()->getId(),
            TestRunMessage::PHASE_CLEANUP,
        ));
    }

    private function handleCleanup(TestRun $run): void
    {
        $this->testRunnerService->cleanupRun($run);
    }
}
