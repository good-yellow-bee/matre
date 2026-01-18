<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TestSuite;
use App\Repository\TestSuiteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/test-suites')]
#[IsGranted('ROLE_USER')]
class TestSuiteApiController extends AbstractController
{
    public function __construct(
        private readonly TestSuiteRepository $testSuiteRepository,
    ) {
    }

    #[Route('', name: 'api_test_suite_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $suites = $this->testSuiteRepository->findActive();

        return $this->json(array_map(
            fn (TestSuite $suite) => [
                'id' => $suite->getId(),
                'name' => $suite->getName(),
                'type' => $suite->getType(),
                'typeLabel' => $suite->getTypeLabel(),
            ],
            $suites,
        ));
    }

    #[Route('/{id}/environments', name: 'api_test_suite_environments', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function environments(TestSuite $suite): JsonResponse
    {
        $environments = $suite->getEnvironments();

        return $this->json(array_map(
            fn ($env) => [
                'id' => $env->getId(),
                'name' => $env->getName(),
            ],
            $environments->toArray(),
        ));
    }
}
