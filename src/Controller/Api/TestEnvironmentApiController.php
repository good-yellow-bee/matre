<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TestEnvironment;
use App\Repository\GlobalEnvVariableRepository;
use App\Repository\TestEnvironmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/test-environments')]
#[IsGranted('ROLE_ADMIN')]
class TestEnvironmentApiController extends AbstractController
{
    public function __construct(
        private readonly GlobalEnvVariableRepository $globalEnvVariableRepository,
        private readonly TestEnvironmentRepository $environmentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_test_environment_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $environments = $this->environmentRepository->findBy(['isActive' => true], ['name' => 'ASC']);

        return $this->json(array_map(fn (TestEnvironment $e) => [
            'id' => $e->getId(),
            'name' => $e->getName(),
        ], $environments));
    }

    #[Route('/{id}', name: 'api_test_environment_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $env = $this->environmentRepository->find($id);

        if (!$env) {
            return $this->json(['error' => 'Test environment not found'], 404);
        }

        return $this->json([
            'id' => $env->getId(),
            'name' => $env->getName(),
            'code' => $env->getCode(),
            'region' => $env->getRegion(),
            'baseUrl' => $env->getBaseUrl(),
            'backendName' => $env->getBackendName(),
            'description' => $env->getDescription(),
            'isActive' => $env->getIsActive(),
            'createdAt' => $env->getCreatedAt()->format('c'),
            'updatedAt' => $env->getUpdatedAt()?->format('c'),
        ]);
    }

    #[Route('', name: 'api_test_environment_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateEnvironmentData($data);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        $env = new TestEnvironment();
        $this->populateEnvironment($env, $data);

        $this->entityManager->persist($env);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Test environment created successfully',
            'id' => $env->getId(),
        ], 201);
    }

    #[Route('/{id}', name: 'api_test_environment_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $env = $this->environmentRepository->find($id);

        if (!$env) {
            return $this->json(['error' => 'Test environment not found'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateEnvironmentData($data, $env);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        $this->populateEnvironment($env, $data);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Test environment updated successfully',
        ]);
    }

    #[Route('/validate-name', name: 'api_test_environment_validate_name', methods: ['POST'])]
    public function validateName(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $name = $data['name'] ?? '';
        $excludeId = $data['excludeId'] ?? null;

        if (strlen($name) < 2) {
            return $this->json([
                'valid' => false,
                'message' => 'Name must be at least 2 characters',
            ]);
        }

        $qb = $this->environmentRepository->createQueryBuilder('e')
            ->where('e.name = :name')
            ->setParameter('name', $name);

        if ($excludeId) {
            $qb->andWhere('e.id != :id')
               ->setParameter('id', $excludeId);
        }

        $exists = null !== $qb->getQuery()->getOneOrNullResult();

        return $this->json([
            'valid' => !$exists,
            'message' => $exists ? 'An environment with this name already exists' : 'Name is available',
        ]);
    }

    #[Route('/validate-code', name: 'api_test_environment_validate_code', methods: ['POST'])]
    public function validateCode(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $code = $data['code'] ?? '';
        $excludeId = $data['excludeId'] ?? null;

        if (empty($code)) {
            return $this->json([
                'valid' => false,
                'message' => 'Code is required',
            ]);
        }

        $qb = $this->environmentRepository->createQueryBuilder('e')
            ->where('e.code = :code')
            ->setParameter('code', $code);

        if ($excludeId) {
            $qb->andWhere('e.id != :id')
               ->setParameter('id', $excludeId);
        }

        $exists = null !== $qb->getQuery()->getOneOrNullResult();

        return $this->json([
            'valid' => !$exists,
            'message' => $exists ? 'An environment with this code already exists' : 'Code is available',
        ]);
    }

    #[Route('/{id}/env-variables', name: 'api_test_environment_env_vars_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function listEnvVariables(TestEnvironment $environment): JsonResponse
    {
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

    #[Route('/{id}/env-variables', name: 'api_test_environment_env_vars_save', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function saveEnvVariables(TestEnvironment $environment, Request $request): JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('env_variable_api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $variables = $data['variables'] ?? [];

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

    /** @return array<string, string> */
    private function validateEnvironmentData(array $data, ?TestEnvironment $existing = null): array
    {
        $errors = [];

        // Validate name
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) < 2) {
            $errors['name'] = 'Name must be at least 2 characters';
        } elseif (strlen($data['name']) > 100) {
            $errors['name'] = 'Name cannot exceed 100 characters';
        } else {
            $qb = $this->environmentRepository->createQueryBuilder('e')
                ->where('e.name = :name')
                ->setParameter('name', $data['name']);

            if ($existing) {
                $qb->andWhere('e.id != :id')
                   ->setParameter('id', $existing->getId());
            }

            if (null !== $qb->getQuery()->getOneOrNullResult()) {
                $errors['name'] = 'An environment with this name already exists';
            }
        }

        // Validate code
        if (empty($data['code'])) {
            $errors['code'] = 'Code is required';
        } elseif (strlen($data['code']) > 50) {
            $errors['code'] = 'Code cannot exceed 50 characters';
        } else {
            $qb = $this->environmentRepository->createQueryBuilder('e')
                ->where('e.code = :code')
                ->setParameter('code', $data['code']);

            if ($existing) {
                $qb->andWhere('e.id != :id')
                   ->setParameter('id', $existing->getId());
            }

            if (null !== $qb->getQuery()->getOneOrNullResult()) {
                $errors['code'] = 'An environment with this code already exists';
            }
        }

        // Validate region
        if (empty($data['region'])) {
            $errors['region'] = 'Region is required';
        } elseif (strlen($data['region']) > 50) {
            $errors['region'] = 'Region cannot exceed 50 characters';
        }

        // Validate baseUrl
        if (empty($data['baseUrl'])) {
            $errors['baseUrl'] = 'Base URL is required';
        } elseif (!filter_var($data['baseUrl'], FILTER_VALIDATE_URL)) {
            $errors['baseUrl'] = 'Please enter a valid URL';
        } elseif (strlen($data['baseUrl']) > 255) {
            $errors['baseUrl'] = 'Base URL cannot exceed 255 characters';
        }

        // Validate backendName
        if (empty($data['backendName'])) {
            $errors['backendName'] = 'Backend name is required';
        } elseif (strlen($data['backendName']) > 100) {
            $errors['backendName'] = 'Backend name cannot exceed 100 characters';
        }

        return $errors;
    }

    private function populateEnvironment(TestEnvironment $env, array $data): void
    {
        $env->setName($data['name']);
        $env->setCode($data['code']);
        $env->setRegion($data['region']);
        $env->setBaseUrl($data['baseUrl']);
        $env->setBackendName($data['backendName']);
        $env->setDescription($data['description'] ?? null);
        $env->setIsActive($data['isActive'] ?? true);
    }

    /** @return array<int, array{name: string, value: string}> */
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
