<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\TestRunRepository;
use App\Service\ArtifactCollectorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/test-runs')]
#[IsGranted('ROLE_ADMIN')]
class TestRunController extends AbstractController
{
    public function __construct(
        private readonly ArtifactCollectorService $artifactCollector,
        private readonly TestRunRepository $testRunRepository,
    ) {
    }

    #[Route('', name: 'admin_test_run_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('spa/index.html.twig');
    }

    #[Route('/new', name: 'admin_test_run_new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('spa/index.html.twig');
    }

    #[Route('/{id}', name: 'admin_test_run_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(): Response
    {
        return $this->render('spa/index.html.twig');
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
}
