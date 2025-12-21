<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TestEnvironmentRepository;
use App\Repository\TestRunRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_USER')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly TestRunRepository $testRunRepository,
        private readonly TestEnvironmentRepository $environmentRepository,
    ) {
    }

    #[Route('', name: 'admin_dashboard')]
    public function index(): Response
    {
        $runStats = $this->testRunRepository->getStatistics();

        return $this->render('admin/dashboard.html.twig', [
            'user' => $this->getUser(),
            'stats' => [
                'totalRuns' => $runStats['total'],
                'passedRuns' => $runStats['completed'],
                'failedRuns' => $runStats['failed'],
                'environments' => $this->environmentRepository->count([]),
            ],
        ]);
    }
}
