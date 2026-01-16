<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TestEnvironment;
use App\Repository\GlobalEnvVariableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API Controller for TestEnvironment env variables management.
 */
#[Route('/api/test-environments')]
#[IsGranted('ROLE_ADMIN')]
class TestEnvironmentApiController extends AbstractController
{
    public function __construct(
        private readonly GlobalEnvVariableRepository $globalEnvVariableRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get env variables for a specific environment.
     * Returns both global (inherited) and environment-specific variables.
     */
    #[Route('/{id}/env-variables', name: 'api_test_environment_env_vars_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function listEnvVariables(TestEnvironment $environment): JsonResponse
    {
        // Get global variables
        $globalVars = $this->globalEnvVariableRepository->findAllOrdered();
        $globalData = [];
        foreach ($globalVars as $var) {
            $globalData[] = [
                'id' => 'global_' . $var->getId(),
                'name' => $var->getName(),
                'value' => $var->getValue(),
                'usedInTests' => $var->getUsedInTests(),
                'description' => $var->getDescription(),
                'isGlobal' => true,
            ];
        }

        // Get environment-specific variables
        $envVarsWithMeta = $environment->getEnvVariablesWithMetadata();
        $envData = [];
        $index = 0;
        foreach ($envVarsWithMeta as $name => $data) {
            $envData[] = [
                'id' => 'env_' . $index++,
                'name' => $name,
                'value' => $data['value'],
                'usedInTests' => $data['usedInTests'],
                'description' => null,
                'isGlobal' => false,
            ];
        }

        return $this->json([
            'global' => $globalData,
            'environment' => $envData,
            'total' => count($globalData) + count($envData),
        ]);
    }

    /**
     * Save env variables for a specific environment.
     */
    #[Route('/{id}/env-variables', name: 'api_test_environment_env_vars_save', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function saveEnvVariables(TestEnvironment $environment, Request $request): JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('env_variable_api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $variables = $data['variables'] ?? [];

        // Build new envVariables array in the new format
        $newEnvVars = [];
        foreach ($variables as $varData) {
            $name = strtoupper(trim($varData['name'] ?? ''));
            if (empty($name)) {
                continue;
            }

            $value = $varData['value'] ?? '';
            $usedInTests = $varData['usedInTests'] ?? null;

            if (!empty($usedInTests)) {
                $newEnvVars[$name] = [
                    'value' => $value,
                    'usedInTests' => $usedInTests,
                ];
            } else {
                $newEnvVars[$name] = $value;
            }
        }

        $environment->setEnvVariables($newEnvVars);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'count' => count($newEnvVars),
            'message' => sprintf('Saved %d environment variable(s)', count($newEnvVars)),
        ]);
    }

    /**
     * Import .env content for a specific environment.
     */
    #[Route('/{id}/env-variables/import', name: 'api_test_environment_env_vars_import', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function importEnvVariables(TestEnvironment $environment, Request $request): JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('env_variable_api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $content = $data['content'] ?? '';

        if (empty($content)) {
            return $this->json(['error' => 'No content provided'], 400);
        }

        $parsed = $this->parseEnvContent($content);

        return $this->json([
            'success' => true,
            'variables' => $parsed,
            'count' => count($parsed),
        ]);
    }

    /**
     * Parse .env file content into array of variables.
     *
     * @return array<int, array{name: string, value: string}>
     */
    private function parseEnvContent(string $content): array
    {
        $lines = explode("\n", $content);
        $variables = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/i', $line, $matches)) {
                $name = strtoupper($matches[1]);
                $value = $matches[2];

                if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                $variables[] = [
                    'name' => $name,
                    'value' => $value,
                ];
            }
        }

        return $variables;
    }
}
