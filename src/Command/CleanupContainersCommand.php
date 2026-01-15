<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\TestEnvironmentRepository;
use App\Service\MagentoContainerPoolService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:containers:cleanup',
    description: 'Clean up per-environment Magento containers',
)]
class CleanupContainersCommand extends Command
{
    public function __construct(
        private readonly MagentoContainerPoolService $containerPool,
        private readonly TestEnvironmentRepository $environmentRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Remove all per-environment containers')
            ->addOption('orphaned', 'o', InputOption::VALUE_NONE, 'Remove containers for deleted environments')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command cleans up per-environment Magento containers:

                    Remove all per-environment containers:
                        <info>php %command.full_name% --all</info>

                    Remove orphaned containers (environments no longer exist):
                        <info>php %command.full_name% --orphaned</info>
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Container Cleanup');

        $all = $input->getOption('all');
        $orphaned = $input->getOption('orphaned');

        if (!$all && !$orphaned) {
            $io->warning('Specify --all or --orphaned');

            return Command::INVALID;
        }

        if ($all) {
            $removed = $this->containerPool->cleanupAllContainers();
            $io->success(sprintf('Removed %d container(s).', $removed));

            return Command::SUCCESS;
        }

        if ($orphaned) {
            $removed = $this->cleanupOrphanedContainers($io);
            $io->success(sprintf('Removed %d orphaned container(s).', $removed));

            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }

    private function cleanupOrphanedContainers(SymfonyStyle $io): int
    {
        $environments = $this->environmentRepository->findAll();
        $validIds = array_map(fn ($e) => $e->getId(), $environments);

        $process = new \Symfony\Component\Process\Process([
            'docker', 'ps', '-a',
            '--filter', 'name=matre_magento_env_',
            '--format', '{{.Names}}',
        ]);
        $process->run();

        $containers = array_filter(explode("\n", trim($process->getOutput())));
        $removed = 0;

        foreach ($containers as $containerName) {
            // Extract environment ID from container name
            if (preg_match('/matre_magento_env_(\d+)$/', $containerName, $matches)) {
                $envId = (int) $matches[1];

                if (!in_array($envId, $validIds, true)) {
                    $io->text(sprintf('Removing orphaned: %s', $containerName));

                    $rmProcess = new \Symfony\Component\Process\Process(['docker', 'rm', '-f', $containerName]);
                    $rmProcess->run();
                    ++$removed;
                }
            }
        }

        return $removed;
    }
}
