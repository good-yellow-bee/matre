<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TestRun;
use App\Form\TestRunType;
use App\Message\TestRunMessage;
use App\Repository\TestRunRepository;
use App\Service\ArtifactCollectorService;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly TestRunRepository $testRunRepository,
        private readonly TestRunnerService $testRunnerService,
        private readonly ArtifactCollectorService $artifactCollector,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route('', name: 'admin_test_run_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/test_run/index.html.twig');
    }

    #[Route('/new', name: 'admin_test_run_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(TestRunType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $run = $this->testRunnerService->createRun(
                $data['environment'],
                $data['type'],
                $data['testFilter'],
                $data['suite'],
                TestRun::TRIGGER_MANUAL
            );

            // Dispatch async execution
            $this->messageBus->dispatch(new TestRunMessage(
                $run->getId(),
                TestRunMessage::PHASE_PREPARE
            ));

            $this->addFlash('success', sprintf('Test run #%d started.', $run->getId()));

            return $this->redirectToRoute('admin_test_run_show', ['id' => $run->getId()]);
        }

        return $this->render('admin/test_run/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_test_run_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(TestRun $run): Response
    {
        $artifacts = $this->artifactCollector->listArtifacts($run);

        return $this->render('admin/test_run/show.html.twig', [
            'run' => $run,
            'artifacts' => $artifacts,
        ]);
    }

    #[Route('/{id}/artifacts/{filename}', name: 'admin_test_run_artifact', methods: ['GET'], requirements: ['id' => '\d+', 'filename' => '.+'])]
    public function artifact(TestRun $run, string $filename): Response
    {
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
            $filename
        );

        return $response;
    }

    #[Route('/{id}/cancel', name: 'admin_test_run_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(Request $request, TestRun $run): Response
    {
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
    public function retry(Request $request, TestRun $run): Response
    {
        if ($this->isCsrfTokenValid('retry' . $run->getId(), $request->request->get('_token'))) {
            $newRun = $this->testRunnerService->retryRun($run);

            // Dispatch async execution
            $this->messageBus->dispatch(new TestRunMessage(
                $newRun->getId(),
                TestRunMessage::PHASE_PREPARE
            ));

            $this->addFlash('success', sprintf('New test run #%d created from retry.', $newRun->getId()));

            return $this->redirectToRoute('admin_test_run_show', ['id' => $newRun->getId()]);
        }

        $this->addFlash('error', 'Invalid CSRF token.');

        return $this->redirectToRoute('admin_test_run_show', ['id' => $run->getId()]);
    }
}
