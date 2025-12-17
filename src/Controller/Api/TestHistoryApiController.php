<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\TestEnvironmentRepository;
use App\Repository\TestResultRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/test-history')]
#[IsGranted('ROLE_USER')]
class TestHistoryApiController extends AbstractController
{
    public function __construct(
        private readonly TestResultRepository $testResultRepository,
        private readonly TestEnvironmentRepository $environmentRepository,
    ) {
    }

    #[Route('', name: 'api_test_history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        $testId = $request->query->get('testId');
        $environmentId = $request->query->getInt('environmentId');
        $limit = min(50, max(1, $request->query->getInt('limit', 20)));

        if (!$testId || !$environmentId) {
            return $this->json(['error' => 'testId and environmentId required'], 400);
        }

        $environment = $this->environmentRepository->find($environmentId);
        if (!$environment) {
            return $this->json(['error' => 'Environment not found'], 404);
        }

        $results = $this->testResultRepository->findHistoryByTestId(
            $testId,
            $environmentId,
            $limit
        );

        $data = array_map(fn ($result) => [
            'id' => $result->getId(),
            'testId' => $result->getTestId(),
            'testName' => $result->getTestName(),
            'status' => $result->getStatus(),
            'duration' => $result->getDuration(),
            'durationFormatted' => $result->getDurationFormatted(),
            'errorMessage' => $result->getErrorMessage(),
            'screenshotPath' => $result->getScreenshotPath(),
            'createdAt' => $result->getCreatedAt()->format('c'),
            'testRun' => [
                'id' => $result->getTestRun()->getId(),
                'status' => $result->getTestRun()->getStatus(),
                'startedAt' => $result->getTestRun()->getStartedAt()?->format('c'),
                'completedAt' => $result->getTestRun()->getCompletedAt()?->format('c'),
                'triggeredBy' => $result->getTestRun()->getTriggeredBy(),
            ],
        ], $results);

        return $this->json([
            'data' => $data,
            'meta' => [
                'testId' => $testId,
                'environmentId' => $environmentId,
                'environmentName' => $environment->getName(),
                'count' => count($results),
            ],
        ]);
    }
}
