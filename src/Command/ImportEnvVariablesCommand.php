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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Import environment variables from .env file and analyze MFTF test usage.
 *
 * This command parses a .env file from TEST_MODULE_REPO, scans MFTF test XML
 * files for {{_ENV.VAR}} patterns, and saves to GlobalEnvVariable.
 *
 * Usage:
 *   # Clone fresh and select environment interactively
 *   php bin/console app:env:import --clone
 *
 *   # Clone and import specific environment
 *   php bin/console app:env:import stage-us --clone
 *
 *   # Import from already cloned module
 *   php bin/console app:env:import stage-us
 *
 *   # Update existing variables
 *   php bin/console app:env:import stage-us --clone --overwrite
 *
 * @see GlobalEnvVariable Entity storing imported variables
 * @see EnvVariableAnalyzerService Service handling parsing and analysis
 */
#[AsCommand(
    name: 'app:env:import',
    description: 'Import .env variables from TEST_MODULE_REPO and analyze MFTF test usage',
)]
class ImportEnvVariablesCommand extends Command
{
    private const ENV_DATA_PATH = 'Cron/data';

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
            ->addArgument('environment', InputArgument::OPTIONAL, 'Environment name (e.g., stage-us, dev-es)')
            ->addOption('clone', 'c', InputOption::VALUE_NONE, 'Clone fresh test module from TEST_MODULE_REPO')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without saving changes')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Update existing variables')
            ->addOption('global', 'g', InputOption::VALUE_NONE, 'Import as global variables (apply to all environments)')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command imports environment variables from TEST_MODULE_REPO:

                    Clone fresh module and select environment interactively:

                        <info>php %command.full_name% --clone</info>

                    Clone and import as environment-specific variables:

                        <info>php %command.full_name% stage-us --clone</info>

                    Import as global variables (apply to all environments):

                        <info>php %command.full_name% stage-us --clone --global</info>

                    Update existing variables:

                        <info>php %command.full_name% stage-us --clone --overwrite</info>

                    The command will:
                      1. Clone TEST_MODULE_REPO to var/test-modules/current (if --clone)
                      2. Discover .env files in Cron/data/ directory
                      3. Parse the selected .env file
                      4. Scan MFTF test XML files for {{_ENV.VAR}} patterns
                      5. Create or update GlobalEnvVariable entities
                      6. Set environment field (null for --global, else environment name)
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $environment = $input->getArgument('environment');
        $clone = $input->getOption('clone');
        $dryRun = $input->getOption('dry-run');
        $overwrite = $input->getOption('overwrite');
        $asGlobal = $input->getOption('global');

        $modulePath = $this->analyzerService->getDefaultModulePath();

        // Clone fresh module if requested
        if ($clone) {
            $io->section('Cloning fresh test module...');
            $io->text([
                sprintf('Repository: %s', $this->moduleCloneService->getModuleRepo()),
                sprintf('Branch: %s', $this->moduleCloneService->getModuleBranch()),
            ]);

            try {
                $this->moduleCloneService->cloneModule($modulePath);
                $io->text(sprintf('<fg=green>✓</> Cloned to: %s', $modulePath));
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to clone module: %s', $e->getMessage()));

                return Command::FAILURE;
            }
        }

        // Check if module exists
        $envDataPath = $modulePath . '/' . self::ENV_DATA_PATH;
        if (!is_dir($envDataPath)) {
            $io->error(sprintf(
                'Module not found at %s. Use --clone to fetch from TEST_MODULE_REPO.',
                $modulePath,
            ));

            return Command::FAILURE;
        }

        // Discover available environments
        $availableEnvs = $this->discoverEnvironments($envDataPath);
        if (empty($availableEnvs)) {
            $io->error(sprintf('No .env.* files found in %s', $envDataPath));

            return Command::FAILURE;
        }

        // Select environment
        if (!$environment) {
            $environment = $io->choice(
                'Select environment to import',
                $availableEnvs,
                $availableEnvs[0] ?? null,
            );
        }

        // Validate environment exists
        $envFile = sprintf('%s/.env.%s', $envDataPath, $environment);
        if (!file_exists($envFile)) {
            $io->error(sprintf(
                'Environment "%s" not found. Available: %s',
                $environment,
                implode(', ', $availableEnvs),
            ));

            return Command::FAILURE;
        }

        $io->title('Import Environment Variables');
        $io->text([
            sprintf('Source file: .env.%s', $environment),
            sprintf('Target: %s', $asGlobal ? 'Global (all environments)' : sprintf('Environment "%s"', $environment)),
            sprintf('Mode: %s', $dryRun ? 'Dry run' : ($overwrite ? 'Overwrite' : 'Create new / Merge')),
        ]);
        $io->newLine();

        // Parse .env file
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

        // Analyze test usage
        $io->section('Analyzing MFTF test usage...');
        $testUsage = $this->analyzerService->analyzeTestUsage($modulePath);
        $io->text(sprintf('Found %d variables used in tests', count($testUsage)));

        // Process variables
        $io->section('Processing variables...');
        $stats = ['new' => 0, 'updated' => 0, 'merged' => 0, 'unchanged' => 0];
        $rows = [];

        foreach ($envVars as $name => $value) {
            $name = strtoupper($name);
            $usedInTests = $testUsage[$name] ?? [];
            $usedInTestsStr = implode(',', $usedInTests);

            // Target environments for this variable
            $targetEnvs = $asGlobal ? null : [$environment];

            // Try to find existing by name+value (for merge)
            $existingByNameValue = $this->globalEnvRepository->findByNameAndValue($name, $value);

            if ($existingByNameValue !== null && !$asGlobal) {
                // Same name+value exists, add environment to it
                if (!$existingByNameValue->appliesToEnvironment($environment)) {
                    $existingByNameValue->addEnvironment($environment);
                    if ($usedInTestsStr) {
                        $existingByNameValue->setUsedInTests($usedInTestsStr);
                    }
                    $status = 'merged';
                    ++$stats['merged'];
                } else {
                    $status = 'unchanged';
                    ++$stats['unchanged'];
                }
            } else {
                // Check if same name exists (different value)
                $existingByName = $this->globalEnvRepository->findByName($name);

                if ($existingByName !== null && $overwrite) {
                    // Overwrite existing
                    $existingByName->setValue($value);
                    $existingByName->setEnvironments($targetEnvs);
                    $existingByName->setUsedInTests($usedInTestsStr);
                    $status = 'updated';
                    ++$stats['updated'];
                } elseif ($existingByName !== null && !$overwrite) {
                    // Different value but not overwriting - create new
                    $entity = new GlobalEnvVariable();
                    $entity->setName($name);
                    $entity->setValue($value);
                    $entity->setEnvironments($targetEnvs);
                    $entity->setUsedInTests($usedInTestsStr);
                    $this->entityManager->persist($entity);
                    $status = 'new';
                    ++$stats['new'];
                } else {
                    // No existing - create new
                    $entity = new GlobalEnvVariable();
                    $entity->setName($name);
                    $entity->setValue($value);
                    $entity->setEnvironments($targetEnvs);
                    $entity->setUsedInTests($usedInTestsStr);
                    $this->entityManager->persist($entity);
                    $status = 'new';
                    ++$stats['new'];
                }
            }

            $displayValue = strlen($value) > 40 ? substr($value, 0, 37) . '...' : $value;
            $displayTests = count($usedInTests) > 3
                ? implode(', ', array_slice($usedInTests, 0, 3)) . sprintf(' (+%d)', count($usedInTests) - 3)
                : implode(', ', $usedInTests);
            $displayEnv = $asGlobal ? 'Global' : $environment;

            $rows[] = [$name, $displayEnv, $displayValue, $displayTests ?: '-', $this->formatStatus($status)];
        }

        $io->table(['Variable', 'Environment', 'Value', 'Tests Using It', 'Status'], $rows);

        if (!$dryRun) {
            $this->entityManager->flush();
            $io->success(sprintf(
                'Import completed: %d new, %d updated, %d merged, %d unchanged',
                $stats['new'],
                $stats['updated'],
                $stats['merged'],
                $stats['unchanged'],
            ));
        } else {
            $io->note(sprintf(
                'Dry run: %d would be created, %d would be updated, %d would be merged, %d unchanged',
                $stats['new'],
                $stats['updated'],
                $stats['merged'],
                $stats['unchanged'],
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * Discover available .env.* files in the data directory.
     *
     * @return string[] Environment names (e.g., ['dev-us', 'stage-es'])
     */
    private function discoverEnvironments(string $dataPath): array
    {
        $environments = [];

        $finder = new Finder();
        $finder->files()->in($dataPath)->name('.env.*')->depth(0)->ignoreDotFiles(false);

        foreach ($finder as $file) {
            $name = $file->getFilename();
            // Extract environment name from .env.{name}
            if (preg_match('/^\.env\.(.+)$/', $name, $matches)) {
                $environments[] = $matches[1];
            }
        }

        sort($environments);

        return $environments;
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
