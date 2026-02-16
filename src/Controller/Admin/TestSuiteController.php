<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TestSuite;
use App\Form\TestSuiteType;
use App\Repository\TestSuiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/test-suites')]
#[IsGranted('ROLE_ADMIN')]
class TestSuiteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TestSuiteRepository $testSuiteRepository,
    ) {
    }

    #[Route('', name: 'admin_test_suite_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/test_suite/index.html.twig', [
            'suites' => $this->testSuiteRepository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_test_suite_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $suite = new TestSuite();
        $suite->setIsActive(true);

        $form = $this->createForm(TestSuiteType::class, $suite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($suite);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Test suite "%s" created.', $suite->getName()));

            return $this->redirectToRoute('admin_test_suite_index');
        }

        return $this->render('admin/test_suite/new.html.twig', [
            'suite' => $suite,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_test_suite_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(TestSuite $suite): Response
    {
        return $this->render('admin/test_suite/show.html.twig', [
            'suite' => $suite,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_test_suite_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, TestSuite $suite): Response
    {
        $form = $this->createForm(TestSuiteType::class, $suite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Test suite "%s" updated.', $suite->getName()));

            return $this->redirectToRoute('admin_test_suite_index');
        }

        return $this->render('admin/test_suite/edit.html.twig', [
            'suite' => $suite,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_test_suite_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, TestSuite $suite): Response
    {
        if ($this->isCsrfTokenValid('delete' . $suite->getId(), $request->request->get('_token'))) {
            $name = $suite->getName();
            $this->entityManager->remove($suite);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Test suite "%s" deleted.', $name));
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_test_suite_index');
    }

    #[Route('/{id}/toggle-active', name: 'admin_test_suite_toggle_active', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleActive(Request $request, TestSuite $suite): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $suite->getId(), $request->request->get('_token'))) {
            $suite->setIsActive(!$suite->isActive());
            $this->entityManager->flush();

            $status = $suite->isActive() ? 'activated' : 'deactivated';
            $this->addFlash('success', sprintf('Test suite "%s" %s.', $suite->getName(), $status));
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_test_suite_index');
    }

    #[Route('/{id}/duplicate', name: 'admin_test_suite_duplicate', methods: ['POST'])]
    public function duplicate(TestSuite $suite, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('duplicate' . $suite->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $copy = new TestSuite();
        $copy->setName($this->testSuiteRepository->findNextAvailableCopyName($suite->getName()));
        $copy->setType($suite->getType());
        $copy->setDescription($suite->getDescription());
        $copy->setTestPattern($suite->getTestPattern());
        $copy->setExcludedTests($suite->getExcludedTests());
        $copy->setCronExpression($suite->getCronExpression());
        $copy->setEstimatedDuration($suite->getEstimatedDuration());
        $copy->setIsActive(true);

        $this->entityManager->persist($copy);
        $this->entityManager->flush();

        $this->addFlash('success', 'Test suite duplicated. Edit below to customize.');

        return $this->redirectToRoute('admin_test_suite_edit', ['id' => $copy->getId()]);
    }
}
