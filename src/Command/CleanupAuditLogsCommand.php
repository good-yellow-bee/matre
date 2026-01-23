<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AuditLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:audit:cleanup',
    description: 'Clean up audit logs older than retention period',
)]
class CleanupAuditLogsCommand extends Command
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Delete logs older than N days', 90)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without deleting')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command removes old audit log entries:

                        <info>php %command.full_name%</info>

                    Delete logs older than 60 days:

                        <info>php %command.full_name% --days=60</info>

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

        if ($days <= 0) {
            $io->error('Days must be a positive number');

            return Command::FAILURE;
        }

        try {
            $cutoff = new \DateTimeImmutable("-{$days} days");
            $io->text(sprintf('Cleaning audit logs older than: %s', $cutoff->format('Y-m-d')));

            $count = $this->auditLogRepository->countOlderThan($cutoff);

            if (0 === $count) {
                $io->success('No audit logs to clean up.');

                return Command::SUCCESS;
            }

            if ($dryRun) {
                $io->note(sprintf('Would delete %d audit log entries (dry run)', $count));
            } else {
                $deleted = $this->auditLogRepository->deleteOlderThan($cutoff);
                $io->success(sprintf('Deleted %d audit log entries', $deleted));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to cleanup audit logs: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
