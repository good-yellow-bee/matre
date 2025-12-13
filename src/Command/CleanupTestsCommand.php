<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\TestReportRepository;
use App\Repository\TestRunRepository;
use App\Service\AllureReportService;
use App\Service\ModuleCloneService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:cleanup',
    description: 'Clean up old test runs, reports, and temporary files',
)]
class CleanupTestsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TestRunRepository $testRunRepository,
        private readonly TestReportRepository $testReportRepository,
        private readonly AllureReportService $allureReportService,
        private readonly ModuleCloneService $moduleCloneService,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Delete runs older than N days', 30)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without making changes')
            ->addOption('reports-only', null, InputOption::VALUE_NONE, 'Only clean up expired reports')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command cleans up old test data:

                        <info>php %command.full_name%</info>

                    Delete runs older than 7 days:

                        <info>php %command.full_name% --days=7</info>

                    Preview what would be deleted:

                        <info>php %command.full_name% --dry-run</info>
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');
        $reportsOnly = $input->getOption('reports-only');

        $io->title('Test Data Cleanup');

        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $days));
        $io->text(sprintf('Cleaning up data older than: %s', $cutoffDate->format('Y-m-d H:i:s')));

        $stats = [
            'reports_deleted' => 0,
            'runs_deleted' => 0,
            'files_cleaned' => 0,
        ];

        // 1. Clean up expired reports
        $io->section('Cleaning up expired reports');
        $expiredReports = $this->testReportRepository->findExpired();
        $stats['reports_deleted'] = count($expiredReports);

        foreach ($expiredReports as $report) {
            $io->text(sprintf('  - Report #%d (Run #%d)', $report->getId(), $report->getTestRun()->getId()));
            if (!$dryRun) {
                $this->entityManager->remove($report);
            }
        }

        if ($reportsOnly) {
            if (!$dryRun) {
                $this->entityManager->flush();
            }
            $io->success(sprintf('Cleaned up %d expired reports.', $stats['reports_deleted']));

            return Command::SUCCESS;
        }

        // 2. Clean up old test runs
        $io->section('Cleaning up old test runs');
        $qb = $this->testRunRepository->createQueryBuilder('r')
            ->where('r.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate);

        $oldRuns = $qb->getQuery()->getResult();
        $stats['runs_deleted'] = count($oldRuns);

        foreach ($oldRuns as $run) {
            $io->text(sprintf(
                '  - Run #%d (%s on %s)',
                $run->getId(),
                $run->getStatus(),
                $run->getCreatedAt()->format('Y-m-d'),
            ));

            // Clean up module directory
            $modulePath = $this->moduleCloneService->getRunTargetPath($run->getId());
            if (is_dir($modulePath)) {
                if (!$dryRun) {
                    $this->moduleCloneService->cleanup($modulePath);
                }
                ++$stats['files_cleaned'];
            }

            if (!$dryRun) {
                $this->entityManager->remove($run);
            }
        }

        // 3. Clean up orphaned Allure results
        $io->section('Cleaning up orphaned Allure results');
        $allureCleaned = $this->allureReportService->cleanupExpired();
        $stats['files_cleaned'] += $allureCleaned;

        // 4. Clean up orphaned module directories
        $io->section('Cleaning up orphaned module directories');
        $modulesPath = $this->projectDir . '/var/test-modules';
        if (is_dir($modulesPath)) {
            $dirs = glob($modulesPath . '/run-*');
            foreach ($dirs as $dir) {
                // Extract run ID from directory name
                if (preg_match('/run-(\d+)$/', $dir, $matches)) {
                    $runId = (int) $matches[1];
                    $run = $this->testRunRepository->find($runId);

                    if (!$run) {
                        $io->text(sprintf('  - Orphaned directory: %s', basename($dir)));
                        if (!$dryRun) {
                            $this->moduleCloneService->cleanup($dir);
                        }
                        ++$stats['files_cleaned'];
                    }
                }
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->newLine();
        $io->table(
            ['Cleaned', 'Count'],
            [
                ['Test runs', $stats['runs_deleted']],
                ['Reports', $stats['reports_deleted']],
                ['Directories', $stats['files_cleaned']],
            ],
        );

        if ($dryRun) {
            $io->note('This was a dry run. No changes were made.');
        } else {
            $io->success('Cleanup completed.');
        }

        return Command::SUCCESS;
    }
}
