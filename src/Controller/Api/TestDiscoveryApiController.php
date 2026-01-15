<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TestSuite;
use App\Service\TestDiscoveryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/test-discovery')]
#[IsGranted('ROLE_USER')]
class TestDiscoveryApiController extends AbstractController
{
    public function __construct(
        private readonly TestDiscoveryService $testDiscoveryService,
    ) {
    }

    /**
     * Get available tests or groups based on type.
     */
    #[Route('', name: 'api_test_discovery', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $type = $request->query->get('type');

        if (!$type) {
            return $this->json([
                'success' => false,
                'error' => 'Type parameter is required',
            ], 400);
        }

        $validTypes = [
            TestSuite::TYPE_MFTF_GROUP,
            TestSuite::TYPE_MFTF_TEST,
            TestSuite::TYPE_PLAYWRIGHT_GROUP,
            TestSuite::TYPE_PLAYWRIGHT_TEST,
        ];

        if (!in_array($type, $validTypes, true)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid type',
            ], 400);
        }

        // Playwright types not yet implemented
        if (in_array($type, [TestSuite::TYPE_PLAYWRIGHT_GROUP, TestSuite::TYPE_PLAYWRIGHT_TEST], true)) {
            return $this->json([
                'success' => true,
                'type' => $type,
                'items' => [],
                'cached' => false,
                'message' => 'Playwright discovery not implemented',
            ]);
        }

        // Check cache availability
        if (!$this->testDiscoveryService->isCacheAvailable()) {
            return $this->json([
                'success' => true,
                'type' => $type,
                'items' => [],
                'cached' => false,
                'message' => 'Test module not cached. Click refresh to clone.',
            ]);
        }

        // Get items based on type
        $items = match ($type) {
            TestSuite::TYPE_MFTF_GROUP => $this->testDiscoveryService->getMftfGroups(),
            TestSuite::TYPE_MFTF_TEST => $this->testDiscoveryService->getMftfTests(),
            default => [],
        };

        $lastUpdated = $this->testDiscoveryService->getLastUpdated();

        return $this->json([
            'success' => true,
            'type' => $type,
            'items' => array_map(
                fn (string $item) => ['value' => $item, 'label' => $item],
                $items,
            ),
            'cached' => true,
            'lastUpdated' => $lastUpdated?->format('c'),
        ]);
    }

    /**
     * Refresh the test module cache.
     */
    #[Route('/refresh', name: 'api_test_discovery_refresh', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function refresh(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('test_discovery', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }

        try {
            $this->testDiscoveryService->refreshCache();

            return $this->json([
                'success' => true,
                'message' => 'Cache refreshed successfully',
                'lastUpdated' => $this->testDiscoveryService->getLastUpdated()?->format('c'),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to refresh cache: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cache status.
     */
    #[Route('/status', name: 'api_test_discovery_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $available = $this->testDiscoveryService->isCacheAvailable();
        $lastUpdated = $available ? $this->testDiscoveryService->getLastUpdated() : null;

        return $this->json([
            'available' => $available,
            'lastUpdated' => $lastUpdated?->format('c'),
        ]);
    }
}
