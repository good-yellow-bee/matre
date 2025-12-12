<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\TestEnvironment;
use App\Repository\TestEnvironmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:test:import-env',
    description: 'Import test environment configurations from .env files',
)]
class ImportEnvironmentsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TestEnvironmentRepository $environmentRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path to directory containing .env.* files')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be imported without making changes')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite existing environments')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command imports environment configurations from .env files:

                        <info>php %command.full_name% /path/to/module/Cron/data</info>

                    Files should be named: .env.{name} (e.g., .env.dev-us, .env.stage-es)

                    Expected variables in each file:
                      - MAGENTO_BASE_URL
                      - MAGENTO_BACKEND_NAME
                      - MAGENTO_ADMIN_USERNAME (optional)
                      - MAGENTO_ADMIN_PASSWORD (optional)
                      - Any other variables will be stored as custom env variables
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('path');
        $dryRun = $input->getOption('dry-run');
        $overwrite = $input->getOption('overwrite');

        if (!is_dir($path)) {
            $io->error(sprintf('Directory not found: %s', $path));

            return Command::FAILURE;
        }

        $io->title('Import Environment Configurations');
        $io->text(sprintf('Scanning: %s', $path));

        // Find .env.* files
        $finder = new Finder();
        $finder->files()->in($path)->name('.env.*');

        if (!$finder->hasResults()) {
            $io->warning('No .env.* files found in the directory.');

            return Command::SUCCESS;
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($finder as $file) {
            $filename = $file->getFilename();
            // Extract name from .env.{name}
            $envName = substr($filename, 5); // Remove ".env."

            if (empty($envName)) {
                $io->warning(sprintf('Skipping invalid file: %s', $filename));
                continue;
            }

            $io->section(sprintf('Processing: %s', $envName));

            // Check if already exists
            $existing = $this->environmentRepository->findOneBy(['name' => $envName]);
            if ($existing && !$overwrite) {
                $io->note(sprintf('Environment "%s" already exists. Use --overwrite to update.', $envName));
                $skipped++;
                continue;
            }

            // Parse env file
            $envData = $this->parseEnvFile($file->getPathname());

            if (!isset($envData['MAGENTO_BASE_URL'])) {
                $io->warning(sprintf('Missing MAGENTO_BASE_URL in %s', $filename));
                $errors++;
                continue;
            }

            // Extract code and region from name (e.g., dev-us -> code=dev, region=us)
            $parts = explode('-', $envName, 2);
            $code = $parts[0];
            $region = $parts[1] ?? 'default';

            $environment = $existing ?? new TestEnvironment();
            $environment->setName($envName);
            $environment->setCode($code);
            $environment->setRegion($region);
            $environment->setBaseUrl($envData['MAGENTO_BASE_URL']);
            $environment->setBackendName($envData['MAGENTO_BACKEND_NAME'] ?? 'admin');

            if (isset($envData['MAGENTO_ADMIN_USERNAME'])) {
                $environment->setAdminUsername($envData['MAGENTO_ADMIN_USERNAME']);
            }
            if (isset($envData['MAGENTO_ADMIN_PASSWORD'])) {
                $environment->setAdminPassword($envData['MAGENTO_ADMIN_PASSWORD']);
            }

            // Store remaining variables as custom env
            $customVars = array_diff_key($envData, array_flip([
                'MAGENTO_BASE_URL',
                'MAGENTO_BACKEND_NAME',
                'MAGENTO_ADMIN_USERNAME',
                'MAGENTO_ADMIN_PASSWORD',
            ]));
            $environment->setEnvVariables($customVars);
            $environment->setIsActive(true);

            $io->table(
                ['Property', 'Value'],
                [
                    ['Name', $envName],
                    ['Code', $code],
                    ['Region', $region],
                    ['Base URL', $envData['MAGENTO_BASE_URL']],
                    ['Backend', $envData['MAGENTO_BACKEND_NAME'] ?? 'admin'],
                    ['Custom vars', count($customVars)],
                ]
            );

            if (!$dryRun) {
                $this->entityManager->persist($environment);
                $imported++;
            } else {
                $io->note('(dry-run) Would import this environment');
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->newLine();
        $io->table(
            ['Result', 'Count'],
            [
                ['Imported', $imported],
                ['Skipped', $skipped],
                ['Errors', $errors],
            ]
        );

        if ($dryRun) {
            $io->note('This was a dry run. No changes were made.');
        } elseif ($imported > 0) {
            $io->success(sprintf('%d environment(s) imported successfully.', $imported));
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function parseEnvFile(string $path): array
    {
        $data = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if (str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=VALUE
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
