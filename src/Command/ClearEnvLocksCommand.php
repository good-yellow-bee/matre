<?php

declare(strict_types=1);

namespace App\Command;

use App\Messenger\Transport\PerEnvironmentDoctrineReceiver;
use App\Repository\TestEnvironmentRepository;
use Predis\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Clear stale environment processing locks from Redis.
 * Use after cancelling test runs if locks are not properly released.
 */
#[AsCommand(
    name: 'app:clear-env-locks',
    description: 'Clear stale environment processing locks from Redis',
)]
class ClearEnvLocksCommand extends Command
{
    public function __construct(
        private readonly TestEnvironmentRepository $envRepository,
        private readonly LoggerInterface $logger,
        private readonly string $lockDsn,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('env-id', null, InputOption::VALUE_OPTIONAL, 'Clear lock for specific environment ID')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Clear locks for all environments')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be cleared without clearing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!str_starts_with($this->lockDsn, 'redis://')) {
            $io->error('This command only works with Redis locks (LOCK_DSN must be redis://)');

            return Command::FAILURE;
        }

        $envId = $input->getOption('env-id');
        $all = $input->getOption('all');
        $dryRun = $input->getOption('dry-run');

        if (!$envId && !$all) {
            $io->error('Specify --env-id=<id> or --all');

            return Command::FAILURE;
        }

        // Parse Redis DSN and connect
        $redisUrl = parse_url($this->lockDsn);
        $redis = new Client([
            'scheme' => 'tcp',
            'host' => $redisUrl['host'] ?? 'localhost',
            'port' => $redisUrl['port'] ?? 6379,
        ]);

        $envIds = [];
        if ($all) {
            $envs = $this->envRepository->findAll();
            $envIds = array_map(fn ($e) => $e->getId(), $envs);
        } else {
            $envIds = [(int) $envId];
        }

        $cleared = 0;
        foreach ($envIds as $id) {
            $lockKey = PerEnvironmentDoctrineReceiver::getLockKeyForEnv($id);

            // Check if lock exists
            $exists = $redis->exists($lockKey);
            if (!$exists) {
                $io->writeln(sprintf('  [skip] %s - not locked', $lockKey));

                continue;
            }

            if ($dryRun) {
                $io->writeln(sprintf('  [dry-run] Would clear: %s', $lockKey));
                ++$cleared;
            } else {
                $redis->del([$lockKey]);
                $io->writeln(sprintf('  [cleared] %s', $lockKey));
                $this->logger->info('Cleared stale env lock', ['envId' => $id, 'lockKey' => $lockKey]);
                ++$cleared;
            }
        }

        if ($cleared > 0) {
            $io->success(sprintf('%s %d lock(s)', $dryRun ? 'Would clear' : 'Cleared', $cleared));
        } else {
            $io->info('No locks to clear');
        }

        return Command::SUCCESS;
    }
}
