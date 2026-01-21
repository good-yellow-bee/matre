<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\TestRun;
use App\Repository\TestRunRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-run:watchdog',
    description: 'Recover stuck test runs that have stalled',
)]
class TestRunWatchdogCommand extends Command
{
    private const DEFAULT_STALE_MINUTES = 30;

    public function __construct(
        private readonly TestRunRepository $testRunRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('stale-minutes', null, InputOption::VALUE_REQUIRED, 'Minutes without update to consider stuck', self::DEFAULT_STALE_MINUTES)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without making changes')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command finds and recovers stuck test runs:

                        <info>php %command.full_name%</info>

                    By default, runs with no updated_at change for 30 minutes are marked as failed.

                    Customize the stale threshold:

                        <info>php %command.full_name% --stale-minutes=60</info>

                    Preview without making changes:

                        <info>php %command.full_name% --dry-run</info>

                    Run via cron every 5 minutes to auto-recover stalled runs.
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $staleMinutesRaw = $input->getOption('stale-minutes');
        if (!is_numeric($staleMinutesRaw) || (int) $staleMinutesRaw < 1) {
            $io->error('stale-minutes must be a positive integer');

            return Command::FAILURE;
        }
        $staleMinutes = (int) $staleMinutesRaw;
        $dryRun = $input->getOption('dry-run');

        $activeStatuses = [
            TestRun::STATUS_PREPARING,
            TestRun::STATUS_CLONING,
            TestRun::STATUS_WAITING,
            TestRun::STATUS_RUNNING,
            TestRun::STATUS_REPORTING,
        ];

        $stuckRuns = $this->testRunRepository->findStuckRuns($activeStatuses, $staleMinutes);

        if (empty($stuckRuns)) {
            $io->success('No stuck test runs found');

            return Command::SUCCESS;
        }

        $io->warning(sprintf('Found %d stuck test run(s)', count($stuckRuns)));
        $recoveredCount = 0;

        foreach ($stuckRuns as $run) {
            $message = sprintf('Run stalled - no progress for %d minutes', $staleMinutes);
            $previousStatus = $run->getStatus();

            $io->text(sprintf(
                '  [#%d] %s - %s → %s (last update: %s)',
                $run->getId(),
                $run->getEnvironment()?->getName() ?? '[deleted]',
                $previousStatus,
                $dryRun ? 'would mark failed' : 'marking failed',
                $run->getUpdatedAt()?->format('Y-m-d H:i:s') ?? 'never',
            ));

            if (!$dryRun) {
                try {
                    $run->markFailed($message);
                    $this->entityManager->flush();
                    ++$recoveredCount;

                    $this->logger->warning('Watchdog recovered stuck test run', [
                        'runId' => $run->getId(),
                        'previousStatus' => $previousStatus,
                        'staleMinutes' => $staleMinutes,
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error('Watchdog failed to recover test run', [
                        'runId' => $run->getId(),
                        'previousStatus' => $previousStatus,
                        'error' => $e->getMessage(),
                    ]);
                    $io->error(sprintf('Failed to recover run #%d: %s', $run->getId(), $e->getMessage()));
                }
            }
        }

        if ($dryRun) {
            $io->note('Dry run - no changes made');
        } else {
            $io->success(sprintf('Recovered %d stuck test run(s)', $recoveredCount));
        }

        return Command::SUCCESS;
    }
}
