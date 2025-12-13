<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\GlobalEnvVariable;
use App\Repository\GlobalEnvVariableRepository;
use App\Service\EnvVariableAnalyzerService;
use App\Service\ModuleCloneService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Import environment variables from .env file and analyze MFTF test usage.
 *
 * This command parses a .env file, scans MFTF test XML files for {{_ENV.VAR}}
 * patterns, and saves variables to GlobalEnvVariable with usedInTests populated.
 *
 * Usage:
 *   # Clone fresh module and import (recommended)
 *   php bin/console app:env:import \
 *     -f var/test-modules/current/Cron/data/.env.stage-us --clone
 *
 *   # Import from existing module
 *   php bin/console app:env:import \
 *     -f var/test-modules/current/Cron/data/.env.stage-us \
 *     -m var/test-modules/current
 *
 *   # Preview without saving (dry run)
 *   php bin/console app:env:import -f .env --dry-run
 *
 *   # Update existing variables
 *   php bin/console app:env:import -f .env --overwrite
 *
 * Patterns detected in MFTF tests:
 *   - {{_ENV.VAR_NAME}} - Environment variable references
 *
 * @see GlobalEnvVariable Entity storing imported variables
 * @see EnvVariableAnalyzerService Service handling parsing and analysis
 */
#[AsCommand(
    name: 'app:env:import',
    description: 'Import .env variables and analyze MFTF test usage',
)]
class ImportEnvVariablesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GlobalEnvVariableRepository $globalEnvRepository,
        private readonly EnvVariableAnalyzerService $analyzerService,
        private readonly ModuleCloneService $moduleCloneService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('env-file', 'f', InputOption::VALUE_REQUIRED, 'Path to .env file to import')
            ->addOption('module-path', 'm', InputOption::VALUE_OPTIONAL, 'Path to test module (default: var/test-modules/current)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without saving changes')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Update existing variables')
            ->addOption('clone', 'c', InputOption::VALUE_NONE, 'Clone fresh test module from TEST_MODULE_REPO before import')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command imports environment variables and analyzes test usage:

                        <info>php %command.full_name% --env-file=path/to/.env</info>

                    Clone fresh module and import (recommended):

                        <info>php %command.full_name% \
                            -f var/test-modules/current/Cron/data/.env.stage-us --clone</info>

                    Import from existing module:

                        <info>php %command.full_name% \
                            -f var/test-modules/current/Cron/data/.env.stage-us \
                            -m var/test-modules/current</info>

                    Preview changes without saving:

                        <info>php %command.full_name% -f .env --dry-run</info>

                    Update existing variables:

                        <info>php %command.full_name% -f .env --overwrite</info>

                    The command will:
                      1. Parse the .env file for KEY=VALUE pairs
                      2. Scan MFTF test XML files for {{_ENV.VAR}} patterns
                      3. Map each variable to the tests that use it
                      4. Create or update GlobalEnvVariable entities
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get options
        $envFile = $input->getOption('env-file');
        $modulePath = $input->getOption('module-path') ?? $this->analyzerService->getDefaultModulePath();
        $dryRun = $input->getOption('dry-run');
        $overwrite = $input->getOption('overwrite');
        $clone = $input->getOption('clone');

        // Clone fresh module if requested
        if ($clone) {
            $io->section('Cloning fresh test module...');
            $io->text([
                sprintf('Repository: %s', $this->moduleCloneService->getModuleRepo()),
                sprintf('Branch: %s', $this->moduleCloneService->getModuleBranch()),
            ]);

            try {
                $targetPath = $this->analyzerService->getDefaultModulePath();
                $this->moduleCloneService->cloneModule($targetPath);
                $modulePath = $targetPath;
                $io->text(sprintf('<fg=green>✓</> Cloned to: %s', $targetPath));
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to clone module: %s', $e->getMessage()));

                return Command::FAILURE;
            }
        }

        // Validate env-file option
        if (!$envFile) {
            $io->error('The --env-file option is required');

            return Command::FAILURE;
        }

        // Validate file exists
        if (!file_exists($envFile)) {
            $io->error(sprintf('File not found: %s', $envFile));

            return Command::FAILURE;
        }

        $io->title('Import Environment Variables');
        $io->text([
            sprintf('Env file: %s', $envFile),
            sprintf('Module path: %s', $modulePath),
            sprintf('Mode: %s', $dryRun ? 'Dry run (preview only)' : ($overwrite ? 'Overwrite existing' : 'Create new only')),
        ]);
        $io->newLine();

        // Step 1: Parse .env file
        $io->section('Parsing .env file...');
        try {
            $envVars = $this->analyzerService->parseEnvFile($envFile);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (empty($envVars)) {
            $io->warning('No variables found in the .env file');

            return Command::SUCCESS;
        }

        $io->text(sprintf('Found %d variables', count($envVars)));

        // Step 2: Analyze test usage
        $io->section('Analyzing MFTF test usage...');
        $testUsage = $this->analyzerService->analyzeTestUsage($modulePath);
        $usedVarsCount = count($testUsage);
        $io->text(sprintf('Found %d variables used in tests', $usedVarsCount));

        // Step 3: Process variables
        $io->section('Processing variables...');
        $stats = ['new' => 0, 'updated' => 0, 'unchanged' => 0];
        $rows = [];

        foreach ($envVars as $name => $value) {
            // Normalize name to uppercase
            $name = strtoupper($name);

            $usedInTests = $testUsage[$name] ?? [];
            $usedInTestsStr = implode(',', $usedInTests);
            $existing = $this->globalEnvRepository->findByName($name);

            if ($existing !== null && !$overwrite) {
                $status = 'unchanged';
                ++$stats['unchanged'];
            } elseif ($existing !== null) {
                $existing->setValue($value);
                $existing->setUsedInTests($usedInTestsStr);
                $status = 'updated';
                ++$stats['updated'];
            } else {
                $entity = new GlobalEnvVariable();
                $entity->setName($name);
                $entity->setValue($value);
                $entity->setUsedInTests($usedInTestsStr);
                $this->entityManager->persist($entity);
                $status = 'new';
                ++$stats['new'];
            }

            // Build display row
            $displayValue = strlen($value) > 40 ? substr($value, 0, 37) . '...' : $value;
            $displayTests = count($usedInTests) > 3
                ? implode(', ', array_slice($usedInTests, 0, 3)) . sprintf(' (+%d)', count($usedInTests) - 3)
                : implode(', ', $usedInTests);

            $rows[] = [$name, $displayValue, $displayTests ?: '-', $this->formatStatus($status)];
        }

        // Display table
        $io->table(['Variable', 'Value', 'Tests Using It', 'Status'], $rows);

        // Flush if not dry-run
        if (!$dryRun) {
            $this->entityManager->flush();
            $io->success(sprintf(
                'Import completed: %d new, %d updated, %d unchanged',
                $stats['new'],
                $stats['updated'],
                $stats['unchanged']
            ));
        } else {
            $io->note(sprintf(
                'Dry run: %d would be created, %d would be updated, %d unchanged',
                $stats['new'],
                $stats['updated'],
                $stats['unchanged']
            ));
        }

        return Command::SUCCESS;
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'new' => '<fg=green>new</>',
            'updated' => '<fg=yellow>updated</>',
            'unchanged' => '<fg=gray>unchanged</>',
            default => $status,
        };
    }
}
