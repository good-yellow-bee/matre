<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\TestEnvironmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/test-history')]
#[IsGranted('ROLE_USER')]
class TestHistoryController extends AbstractController
{
    public function __construct(
        private readonly TestEnvironmentRepository $environmentRepository,
    ) {
    }

    #[Route('', name: 'admin_test_history', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $testId = $request->query->get('testId');
        $environmentId = $request->query->getInt('environmentId');

        $environment = null;
        if ($environmentId) {
            $environment = $this->environmentRepository->find($environmentId);
        }

        return $this->render('admin/test_history/index.html.twig', [
            'testId' => $testId,
            'environmentId' => $environmentId,
            'environment' => $environment,
        ]);
    }
}
