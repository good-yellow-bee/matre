<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\TestRun;
use App\Message\TestRunMessage;
use App\Repository\TestEnvironmentRepository;
use App\Repository\TestSuiteRepository;
use App\Service\TestRunnerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:test:run',
    description: 'Run MFTF or Playwright tests manually',
)]
class TestRunCommand extends Command
{
    public function __construct(
        private readonly TestEnvironmentRepository $environmentRepository,
        private readonly TestSuiteRepository $suiteRepository,
        private readonly TestRunnerService $testRunnerService,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'Test type: mftf, playwright, or both')
            ->addArgument('environment', InputArgument::REQUIRED, 'Environment name (e.g., dev-us)')
            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, 'Test filter (test name, group, or pattern)')
            ->addOption('suite', 's', InputOption::VALUE_OPTIONAL, 'Test suite name')
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Run synchronously (wait for completion)')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command runs tests against a target environment:

                        <info>php %command.full_name% mftf dev-us</info>

                    Run specific test:

                        <info>php %command.full_name% mftf dev-us --filter="MOEC1625Test"</info>

                    Run a test suite:

                        <info>php %command.full_name% mftf dev-us --suite="Checkout Tests"</info>

                    Run Playwright tests:

                        <info>php %command.full_name% playwright stage-es --filter="@checkout"</info>
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $type = strtolower($input->getArgument('type'));
        $envName = $input->getArgument('environment');
        $filter = $input->getOption('filter');
        $suiteName = $input->getOption('suite');
        $sync = $input->getOption('sync');

        // Validate type
        if (!in_array($type, [TestRun::TYPE_MFTF, TestRun::TYPE_PLAYWRIGHT, TestRun::TYPE_BOTH], true)) {
            $io->error(sprintf('Invalid test type: %s. Use mftf, playwright, or both.', $type));

            return Command::FAILURE;
        }

        // Find environment
        $environment = $this->environmentRepository->findOneBy(['name' => $envName]);
        if (!$environment) {
            $io->error(sprintf('Environment not found: %s', $envName));

            $available = $this->environmentRepository->findAllOrdered();
            if ($available) {
                $io->note('Available environments: ' . implode(', ', array_map(fn ($e) => $e->getName(), $available)));
            }

            return Command::FAILURE;
        }

        if (!$environment->isActive()) {
            $io->warning(sprintf('Environment "%s" is inactive.', $envName));
        }

        // Find suite if specified
        $suite = null;
        if ($suiteName) {
            $suite = $this->suiteRepository->findOneBy(['name' => $suiteName]);
            if (!$suite) {
                $io->error(sprintf('Test suite not found: %s', $suiteName));

                return Command::FAILURE;
            }
            // Use suite pattern if no filter provided
            if (!$filter) {
                $filter = $suite->getTestPattern();
            }
        }

        $io->title('Starting Test Run');
        $io->table(
            ['Property', 'Value'],
            [
                ['Type', strtoupper($type)],
                ['Environment', $environment->getName()],
                ['Base URL', $environment->getBaseUrl()],
                ['Filter', $filter ?: '(all tests)'],
                ['Suite', $suite ? $suite->getName() : '(none)'],
            ],
        );

        // Create test run
        $run = $this->testRunnerService->createRun(
            $environment,
            $type,
            $filter,
            $suite,
            TestRun::TRIGGER_MANUAL,
        );

        $io->success(sprintf('Test run #%d created.', $run->getId()));

        if ($sync) {
            $io->note('Running synchronously...');

            try {
                $this->testRunnerService->prepareRun($run);
                $io->info('Module cloned, executing tests...');

                $this->testRunnerService->executeRun($run);
                $io->info('Tests completed, generating reports...');

                $this->testRunnerService->generateReports($run);

                $counts = $run->getResultCounts();
                $io->newLine();
                $io->table(
                    ['Status', 'Count'],
                    [
                        ['Passed', $counts['passed']],
                        ['Failed', $counts['failed']],
                        ['Skipped', $counts['skipped']],
                        ['Broken', $counts['broken']],
                    ],
                );

                if ($counts['failed'] > 0 || $counts['broken'] > 0) {
                    $io->warning('Some tests failed or are broken.');

                    return Command::FAILURE;
                }

                $io->success('All tests passed!');
            } catch (\Throwable $e) {
                $io->error(sprintf('Test run failed: %s', $e->getMessage()));

                return Command::FAILURE;
            } finally {
                $this->testRunnerService->cleanupRun($run);
            }
        } else {
            // Dispatch async execution
            $this->messageBus->dispatch(new TestRunMessage(
                $run->getId(),
                TestRunMessage::PHASE_PREPARE,
            ));

            $io->note([
                'Test run dispatched for async execution.',
                sprintf('Monitor progress at: /admin/test-runs/%d', $run->getId()),
            ]);
        }

        return Command::SUCCESS;
    }
}
