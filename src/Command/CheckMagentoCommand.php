<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:test:check-magento',
    description: 'Check Magento installation status in Docker',
)]
class CheckMagentoCommand extends Command
{
    public function __construct(
        private readonly string $magentoContainer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Magento Installation Check');

        $checks = [];

        // Check if Magento container is running
        $io->section('Docker Container Status');

        $process = new Process(['docker', 'ps', '--filter', 'name=' . $this->magentoContainer, '--format', '{{.Status}}']);
        $process->run();

        if ($process->isSuccessful() && !empty(trim($process->getOutput()))) {
            $checks['container'] = ['Magento Container', 'Running', 'success'];
            $io->text(sprintf('Status: %s', trim($process->getOutput())));
        } else {
            $checks['container'] = ['Magento Container', 'Not running', 'error'];
            $io->error('Magento container is not running. Start with: docker-compose up -d magento');

            return Command::FAILURE;
        }

        // Check Magento version
        $io->section('Magento Installation');

        $process = new Process([
            'docker', 'exec', $this->magentoContainer,
            'php', 'bin/magento', '--version',
        ]);
        $process->run();

        if ($process->isSuccessful()) {
            $version = trim($process->getOutput());
            $checks['magento'] = ['Magento Version', $version, 'success'];
            $io->text($version);
        } else {
            $checks['magento'] = ['Magento Version', 'Not installed', 'warning'];
            $io->warning([
                'Magento is not installed.',
                sprintf('Run: docker exec %s /usr/local/bin/install-magento.sh', $this->magentoContainer),
            ]);
        }

        // Check MFTF
        $io->section('MFTF Status');

        $process = new Process([
            'docker', 'exec', $this->magentoContainer,
            'bash', '-c', 'test -f vendor/bin/mftf && echo "installed"',
        ]);
        $process->run();

        if ('installed' === trim($process->getOutput())) {
            $checks['mftf'] = ['MFTF Binary', 'Installed', 'success'];

            // Check MFTF version
            $versionProcess = new Process([
                'docker', 'exec', $this->magentoContainer,
                'bash', '-c', 'vendor/bin/mftf --version',
            ]);
            $versionProcess->run();
            $io->text(trim($versionProcess->getOutput()));
        } else {
            $checks['mftf'] = ['MFTF Binary', 'Not installed', 'error'];
            $io->error('MFTF is not installed. Run Magento setup first.');
        }

        // Check Selenium connectivity
        $io->section('Selenium Grid');

        $process = new Process(['curl', '-s', 'http://selenium-hub:4444/status']);
        $process->setTimeout(10);
        $process->run();

        if ($process->isSuccessful()) {
            $status = json_decode($process->getOutput(), true);
            if (isset($status['value']['ready']) && $status['value']['ready']) {
                $nodeCount = count($status['value']['nodes'] ?? []);
                $checks['selenium'] = ['Selenium Grid', sprintf('Ready (%d nodes)', $nodeCount), 'success'];
                $io->text(sprintf('Nodes available: %d', $nodeCount));
            } else {
                $checks['selenium'] = ['Selenium Grid', 'Not ready', 'warning'];
            }
        } else {
            $checks['selenium'] = ['Selenium Grid', 'Unreachable', 'warning'];
            $io->warning('Cannot connect to Selenium Hub. Check docker-compose.');
        }

        // Check Allure service
        $io->section('Allure Report Service');

        $process = new Process(['curl', '-s', 'http://allure:5050/allure-docker-service/version']);
        $process->setTimeout(10);
        $process->run();

        if ($process->isSuccessful()) {
            $version = json_decode($process->getOutput(), true);
            $checks['allure'] = ['Allure Service', $version['data']['version'] ?? 'Running', 'success'];
            $io->text(sprintf('Version: %s', $version['data']['version'] ?? 'unknown'));
        } else {
            $checks['allure'] = ['Allure Service', 'Unreachable', 'warning'];
            $io->warning('Cannot connect to Allure service. Reports may not generate.');
        }

        // Summary
        $io->section('Summary');

        $rows = [];
        $hasErrors = false;
        foreach ($checks as $check) {
            [$name, $status, $level] = $check;
            $icon = match ($level) {
                'success' => '✅',
                'warning' => '⚠️',
                'error' => '❌',
                default => '•',
            };
            $rows[] = [$icon, $name, $status];
            if ('error' === $level) {
                $hasErrors = true;
            }
        }

        $io->table(['', 'Component', 'Status'], $rows);

        if ($hasErrors) {
            $io->error('Some components are not ready. Please fix the issues above.');

            return Command::FAILURE;
        }

        $io->success('All components are ready for test execution.');

        return Command::SUCCESS;
    }
}
