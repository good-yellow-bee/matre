<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TestEnvironment;
use App\Form\TestEnvironmentType;
use App\Repository\TestEnvironmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/test-environments')]
#[IsGranted('ROLE_ADMIN')]
class TestEnvironmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TestEnvironmentRepository $testEnvironmentRepository,
    ) {
    }

    #[Route('', name: 'admin_test_environment_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/test_environment/index.html.twig', [
            'environments' => $this->testEnvironmentRepository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_test_environment_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $environment = new TestEnvironment();
        $environment->setIsActive(true);

        $form = $this->createForm(TestEnvironmentType::class, $environment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($environment);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Environment "%s" created.', $environment->getName()));

            return $this->redirectToRoute('admin_test_environment_index');
        }

        return $this->render('admin/test_environment/new.html.twig', [
            'environment' => $environment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_test_environment_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(TestEnvironment $environment): Response
    {
        return $this->render('admin/test_environment/show.html.twig', [
            'environment' => $environment,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_test_environment_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, TestEnvironment $environment): Response
    {
        $form = $this->createForm(TestEnvironmentType::class, $environment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Environment "%s" updated.', $environment->getName()));

            return $this->redirectToRoute('admin_test_environment_index');
        }

        return $this->render('admin/test_environment/edit.html.twig', [
            'environment' => $environment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_test_environment_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, TestEnvironment $environment): Response
    {
        if ($this->isCsrfTokenValid('delete'.$environment->getId(), $request->request->get('_token'))) {
            $name = $environment->getName();
            $this->entityManager->remove($environment);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Environment "%s" deleted.', $name));
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_test_environment_index');
    }

    #[Route('/{id}/toggle-active', name: 'admin_test_environment_toggle_active', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleActive(Request $request, TestEnvironment $environment): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$environment->getId(), $request->request->get('_token'))) {
            $environment->setIsActive(!$environment->isActive());
            $this->entityManager->flush();

            $status = $environment->isActive() ? 'activated' : 'deactivated';
            $this->addFlash('success', sprintf('Environment "%s" %s.', $environment->getName(), $status));
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_test_environment_index');
    }
}
