<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TestRun;
use App\Entity\User;
use App\Form\TestRunType;
use App\Message\TestRunMessage;
use App\Repository\TestRunRepository;
use App\Repository\TestSuiteRepository;
use App\Repository\UserRepository;
use App\Service\AllureStepParserService;
use App\Service\ArtifactCollectorService;
use App\Service\NotificationService;
use App\Service\TestRunnerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/test-runs')]
#[IsGranted('ROLE_ADMIN')]
class TestRunController extends AbstractController
{
    public function __construct(
        private readonly TestRunnerService $testRunnerService,
        private readonly ArtifactCollectorService $artifactCollector,
        private readonly AllureStepParserService $allureStepParser,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly TestRunRepository $testRunRepository,
        private readonly TestSuiteRepository $testSuiteRepository,
        private readonly NotificationService $notificationService,
        private readonly UserRepository $userRepository,
        private readonly string $noVncUrl,
        private readonly string $allurePublicUrl,
    ) {
    }

    #[Route('', name: 'admin_test_run_index', methods: ['GET'])]
    public function index(): Response
    {
        $suites = $this->testSuiteRepository->findAllOrdered();

        return $this->render('admin/test_run/index.html.twig', [
            'suites' => array_map(fn ($s) => ['id' => $s->getId(), 'name' => $s->getName()], $suites),
        ]);
    }

    #[Route('/new', name: 'admin_test_run_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(TestRunType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $suite = $data['suite'];

            // Derive test type from suite type
            $type = match (true) {
                str_starts_with($suite->getType(), 'mftf') => TestRun::TYPE_MFTF,
                str_starts_with($suite->getType(), 'playwright') => TestRun::TYPE_PLAYWRIGHT,
                default => TestRun::TYPE_MFTF,
            };

            $run = $this->testRunnerService->createRun(
                $data['environment'],
                $type,
                $suite->getTestPattern(),
                $suite,
                TestRun::TRIGGER_MANUAL,
                filter_var($request->request->all('test_run')['sendNotifications'] ?? '1', FILTER_VALIDATE_BOOLEAN),
                $this->getUser(),
            );

            // Dispatch async execution
            $this->messageBus->dispatch(new TestRunMessage(
                $run->getId(),
                $run->getEnvironment()->getId(),
                TestRunMessage::PHASE_PREPARE,
            ));

            $this->addFlash('success', sprintf('Test run #%d started.', $run->getId()));

            return $this->redirectToRoute('admin_test_run_show', ['id' => $run->getId()]);
        }

        return $this->render('admin/test_run/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_test_run_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $run = $this->testRunRepository->find($id);
        if (!$run) {
            $this->addFlash('error', sprintf('Test run #%d does not exist.', $id));

            return $this->redirectToRoute('admin_test_run_index');
        }

        $artifacts = $this->artifactCollector->listArtifacts($run);
        $artifactDisplay = $this->artifactCollector->groupArtifactsForDisplay($artifacts);

        return $this->render('admin/test_run/show.html.twig', [
            'run' => $run,
            'artifacts' => $artifacts,
            'artifactDisplay' => $artifactDisplay,
            'vnc_url' => $this->noVncUrl,
            'allure_public_url' => $this->allurePublicUrl,
        ]);
    }

    #[Route('/{id}/artifacts/{filename}', name: 'admin_test_run_artifact', methods: ['GET'], requirements: ['id' => '\d+', 'filename' => '.+'])]
    public function artifact(int $id, string $filename): Response
    {
        $run = $this->testRunRepository->find($id);
        if (!$run) {
            $this->addFlash('error', sprintf('Test run #%d does not exist.', $id));

            return $this->redirectToRoute('admin_test_run_index');
        }

        // Security: only allow specific extensions
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'html', 'htm', 'json'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions, true)) {
            throw $this->createNotFoundException('File type not allowed');
        }

        if (!$this->artifactCollector->artifactExists($run, $filename)) {
            throw $this->createNotFoundException('Artifact not found');
        }

        $filePath = $this->artifactCollector->getArtifactFilePath($run, $filename);

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $filename,
        );

        return $response;
    }

    #[Route('/{id}/cancel', name: 'admin_test_run_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(Request $request, int $id): Response
    {
        $run = $this->testRunRepository->find($id);
        if (!$run) {
            $this->addFlash('error', sprintf('Test run #%d does not exist.', $id));

            return $this->redirectToRoute('admin_test_run_index');
        }

        if ($this->isCsrfTokenValid('cancel' . $run->getId(), $request->request->get('_token'))) {
            if ($run->canBeCancelled()) {
                $this->testRunnerService->cancelRun($run);
                $this->addFlash('success', sprintf('Test run #%d cancelled.', $run->getId()));
            } else {
                $this->addFlash('error', 'Test run cannot be cancelled in current state.');
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_test_run_show', ['id' => $run->getId()]);
    }

    #[Route('/{id}/retry', name: 'admin_test_run_retry', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function retry(Request $request, int $id): Response
    {
        $run = $this->testRunRepository->find($id);
        if (!$run) {
            $this->addFlash('error', sprintf('Test run #%d does not exist.', $id));

            return $this->redirectToRoute('admin_test_run_index');
        }

        if ($this->isCsrfTokenValid('retry' . $run->getId(), $request->request->get('_token'))) {
            $newRun = $this->testRunnerService->retryRun($run, $this->getUser());

            // Dispatch async execution
            $this->messageBus->dispatch(new TestRunMessage(
                $newRun->getId(),
                $newRun->getEnvironment()->getId(),
                TestRunMessage::PHASE_PREPARE,
            ));

            $this->addFlash('success', sprintf('New test run #%d created from retry.', $newRun->getId()));

            return $this->redirectToRoute('admin_test_run_show', ['id' => $newRun->getId()]);
        }

        $this->addFlash('error', 'Invalid CSRF token.');

        return $this->redirectToRoute('admin_test_run_show', ['id' => $run->getId()]);
    }

    #[Route('/{id}/retry-failed', name: 'admin_test_run_retry_failed', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function retryFailed(Request $request, int $id): Response
    {
        $run = $this->testRunRepository->find($id);
        if (!$run) {
            $this->addFlash('error', sprintf('Test run #%d does not exist.', $id));

            return $this->redirectToRoute('admin_test_run_index');
        }

        if ($this->isCsrfTokenValid('retry_failed' . $run->getId(), $request->request->get('_token'))) {
            $newRun = $this->testRunnerService->retryFailedRun($run, $this->getUser());

            if (null === $newRun) {
                $this->addFlash('warning', 'No retryable failures found. Only WebDriver/infrastructure errors can be retried.');

                return $this->redirectToRoute('admin_test_run_show', ['id' => $run->getId()]);
            }

            // Dispatch async execution
            $this->messageBus->dispatch(new TestRunMessage(
                $newRun->getId(),
                $newRun->getEnvironment()->getId(),
                TestRunMessage::PHASE_PREPARE,
            ));

            $retryCount = \count($newRun->getRetryTestIdsArray());
            $this->addFlash('success', sprintf('Retry run #%d created for %d failed test(s).', $newRun->getId(), $retryCount));

            return $this->redirectToRoute('admin_test_run_show', ['id' => $newRun->getId()]);
        }

        $this->addFlash('error', 'Invalid CSRF token.');

        return $this->redirectToRoute('admin_test_run_show', ['id' => $run->getId()]);
    }

    #[Route('/{id}/resend-notification', name: 'admin_test_run_resend_notification', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resendNotification(Request $request, int $id): Response
    {
        $run = $this->testRunRepository->find($id);
        if (!$run) {
            $this->addFlash('error', sprintf('Test run #%d does not exist.', $id));

            return $this->redirectToRoute('admin_test_run_index');
        }

        if (!$this->isCsrfTokenValid('resend_notification' . $run->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_test_run_show', ['id' => $run->getId()]);
        }

        if (!$run->isFinished()) {
            $this->addFlash('error', 'Can only resend notifications for finished runs.');

            return $this->redirectToRoute('admin_test_run_show', ['id' => $run->getId()]);
        }

        $slackSent = false;
        $emailSent = false;

        if ($this->userRepository->shouldSendSlackNotification($run)) {
            $this->notificationService->sendSlackNotification($run);
            $slackSent = true;
        }

        $usersToEmail = $this->userRepository->findUsersToNotifyByEmail($run);
        $recipients = array_map(static fn (User $u) => $u->getEmail(), $usersToEmail);
        if (!empty($recipients)) {
            $this->notificationService->sendEmailNotification($run, $recipients);
            $emailSent = true;
        }

        if ($slackSent || $emailSent) {
            $channels = array_filter(['Slack' => $slackSent, 'Email' => $emailSent], fn ($v) => $v);
            $this->addFlash('success', 'Notification sent via: ' . implode(', ', array_keys($channels)));
        } else {
            $this->addFlash('warning', 'No users subscribed to notifications for this environment.');
        }

        return $this->redirectToRoute('admin_test_run_show', ['id' => $run->getId()]);
    }
}
