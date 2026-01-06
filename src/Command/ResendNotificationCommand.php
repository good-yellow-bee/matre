<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\TestRunRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notification:resend',
    description: 'Resend notification for a test run',
)]
class ResendNotificationCommand extends Command
{
    public function __construct(
        private readonly TestRunRepository $testRunRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('run-id', InputArgument::OPTIONAL, 'Test run ID (defaults to latest)')
            ->addOption('slack', null, InputOption::VALUE_NONE, 'Force send Slack notification')
            ->addOption('email', null, InputOption::VALUE_NONE, 'Force send email notification');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $runId = $input->getArgument('run-id');

        if ($runId) {
            $run = $this->testRunRepository->find((int) $runId);
        } else {
            $run = $this->testRunRepository->findOneBy([], ['id' => 'DESC']);
        }

        if (!$run) {
            $io->error('Test run not found');

            return Command::FAILURE;
        }

        if (!$run->isFinished()) {
            $io->error(sprintf('Test run #%d is still %s', $run->getId(), $run->getStatus()));

            return Command::FAILURE;
        }

        $io->title(sprintf('Resending notification for Test Run #%d', $run->getId()));
        $io->table(
            ['Field', 'Value'],
            [
                ['Status', $run->getStatus()],
                ['Environment', $run->getEnvironment()->getName()],
                ['Type', $run->getType()],
            ],
        );

        $forceSlack = $input->getOption('slack');
        $forceEmail = $input->getOption('email');
        $slackSent = false;
        $emailSent = false;

        // Slack
        if ($forceSlack || $this->userRepository->shouldSendSlackNotification($run)) {
            $io->text('Sending Slack notification...');
            $this->notificationService->sendSlackNotification($run);
            $slackSent = true;
            $io->text('✓ Slack sent');
        }

        // Email
        $usersToEmail = $this->userRepository->findUsersToNotifyByEmail($run);
        $recipients = array_map(static fn (User $u) => $u->getEmail(), $usersToEmail);

        if ($forceEmail || !empty($recipients)) {
            if (!empty($recipients)) {
                $io->text(sprintf('Sending email to %d recipient(s): %s', count($recipients), implode(', ', $recipients)));
                $this->notificationService->sendEmailNotification($run, $recipients);
                $emailSent = true;
                $io->text('✓ Email sent');
            } else {
                $io->warning('No users subscribed to email notifications for this environment');
            }
        }

        if ($slackSent || $emailSent) {
            $io->success('Notification resent');
        } else {
            $io->warning('No notifications sent - no users subscribed');
        }

        return Command::SUCCESS;
    }
}
