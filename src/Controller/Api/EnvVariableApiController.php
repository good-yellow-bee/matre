<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\GlobalEnvVariable;
use App\Repository\GlobalEnvVariableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * API Controller for Global Environment Variables.
 */
#[Route('/api/env-variables')]
#[IsGranted('ROLE_ADMIN')]
class EnvVariableApiController extends AbstractController
{
    public function __construct(
        private readonly GlobalEnvVariableRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * List all global environment variables.
     */
    #[Route('/list', name: 'api_env_variables_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $environment = $request->query->get('environment');
        $sort = $request->query->get('sort', 'name');
        $order = $request->query->get('order', 'asc');

        $validSorts = ['id', 'name', 'value', 'usedInTests', 'createdAt'];
        if (!in_array($sort, $validSorts, true)) {
            $sort = 'name';
        }

        $order = 'DESC' === strtoupper($order) ? 'DESC' : 'ASC';

        // Use native SQL for JSON filtering if environment specified
        if (null !== $environment && 'global' !== $environment) {
            $conn = $this->entityManager->getConnection();
            $sql = 'SELECT * FROM matre_global_env_variables v
                    WHERE JSON_CONTAINS(v.environments, :env)';
            $params = ['env' => json_encode($environment)];

            if ('' !== $search) {
                $sql .= ' AND (LOWER(v.name) LIKE :search OR LOWER(v.description) LIKE :search OR LOWER(v.used_in_tests) LIKE :search)';
                $params['search'] = '%' . mb_strtolower($search) . '%';
            }

            $sql .= " ORDER BY v.{$sort} {$order}";
            $rawResults = $conn->executeQuery($sql, $params)->fetchAllAssociative();

            $data = array_map(static fn (array $row) => [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'value' => $row['value'],
                'environments' => json_decode($row['environments'], true),
                'usedInTests' => $row['used_in_tests'],
                'description' => $row['description'],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at'],
            ], $rawResults);
        } else {
            $qb = $this->repository->createQueryBuilder('v')
                ->orderBy('v.' . $sort, $order);

            if ('' !== $search) {
                $qb
                    ->andWhere('LOWER(v.name) LIKE :search OR LOWER(v.description) LIKE :search OR LOWER(v.usedInTests) LIKE :search')
                    ->setParameter('search', '%' . mb_strtolower($search) . '%');
            }

            // Filter global only (environments IS NULL)
            if ('global' === $environment) {
                $qb->andWhere('v.environments IS NULL');
            }

            $results = $qb->getQuery()->getResult();

            $data = array_map(static fn (GlobalEnvVariable $var) => [
                'id' => $var->getId(),
                'name' => $var->getName(),
                'value' => $var->getValue(),
                'environments' => $var->getEnvironments(),
                'usedInTests' => $var->getUsedInTests(),
                'description' => $var->getDescription(),
                'createdAt' => $var->getCreatedAt()->format('c'),
                'updatedAt' => $var->getUpdatedAt()?->format('c'),
            ], $results);
        }

        return $this->json([
            'data' => $data,
            'total' => count($data),
            'environments' => $this->repository->getDistinctEnvironments(),
        ]);
    }

    /**
     * Get single variable.
     */
    #[Route('/{id}', name: 'api_env_variables_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $var = $this->repository->find($id);

        if (!$var) {
            return $this->json(['error' => 'Variable not found'], 404);
        }

        return $this->json([
            'id' => $var->getId(),
            'name' => $var->getName(),
            'value' => $var->getValue(),
            'environments' => $var->getEnvironments(),
            'usedInTests' => $var->getUsedInTests(),
            'description' => $var->getDescription(),
            'createdAt' => $var->getCreatedAt()->format('c'),
            'updatedAt' => $var->getUpdatedAt()?->format('c'),
        ]);
    }

    /**
     * Create new variable.
     */
    #[Route('', name: 'api_env_variables_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('env_variable_api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $var = new GlobalEnvVariable();
        $var->setName(strtoupper(trim($data['name'] ?? '')));
        $var->setValue($data['value'] ?? '');
        $var->setEnvironments($data['environments'] ?? null);
        $var->setUsedInTests($data['usedInTests'] ?? null);
        $var->setDescription($data['description'] ?? null);

        $errors = $this->validator->validate($var);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], 400);
        }

        $this->entityManager->persist($var);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'id' => $var->getId(),
            'message' => sprintf('Variable "%s" created', $var->getName()),
        ], 201);
    }

    /**
     * Update existing variable.
     */
    #[Route('/{id}', name: 'api_env_variables_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('env_variable_api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $var = $this->repository->find($id);

        if (!$var) {
            return $this->json(['error' => 'Variable not found'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['name'])) {
            $var->setName(strtoupper(trim($data['name'])));
        }
        if (array_key_exists('value', $data)) {
            $var->setValue($data['value'] ?? '');
        }
        if (array_key_exists('environments', $data)) {
            $var->setEnvironments($data['environments']);
        }
        if (array_key_exists('usedInTests', $data)) {
            $var->setUsedInTests($data['usedInTests']);
        }
        if (array_key_exists('description', $data)) {
            $var->setDescription($data['description']);
        }

        $errors = $this->validator->validate($var);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], 400);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => sprintf('Variable "%s" updated', $var->getName()),
        ]);
    }

    /**
     * Delete variable.
     */
    #[Route('/{id}', name: 'api_env_variables_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('env_variable_api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $var = $this->repository->find($id);

        if (!$var) {
            return $this->json(['error' => 'Variable not found'], 404);
        }

        $name = $var->getName();
        $this->entityManager->remove($var);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => sprintf('Variable "%s" deleted', $name),
        ]);
    }

    /**
     * Bulk save/update variables (for Vue grid).
     */
    #[Route('/bulk', name: 'api_env_variables_bulk', methods: ['POST'])]
    public function bulk(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('env_variable_api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $variables = $data['variables'] ?? [];

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($variables as $index => $varData) {
            $name = strtoupper(trim($varData['name'] ?? ''));
            $environments = $varData['environments'] ?? null;

            if (empty($name)) {
                continue;
            }

            $id = $varData['id'] ?? null;
            $var = $id ? $this->repository->find($id) : null;

            if (!$var) {
                // Check if exists by name only (for updating)
                $var = $this->repository->findByName($name);
            }

            if (!$var) {
                $var = new GlobalEnvVariable();
                $var->setName($name);
                $this->entityManager->persist($var);
                ++$created;
            } else {
                $var->setName($name);
                ++$updated;
            }

            $var->setValue($varData['value'] ?? '');
            $var->setEnvironments($environments);
            $var->setUsedInTests($varData['usedInTests'] ?? null);
            $var->setDescription($varData['description'] ?? null);

            $validationErrors = $this->validator->validate($var);
            if (count($validationErrors) > 0) {
                foreach ($validationErrors as $error) {
                    $errors[] = sprintf('Row %d (%s): %s', $index + 1, $name, $error->getMessage());
                }
            }
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 400);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'created' => $created,
            'updated' => $updated,
            'message' => sprintf('%d created, %d updated', $created, $updated),
        ]);
    }

    /**
     * Import from .env file content.
     */
    #[Route('/import', name: 'api_env_variables_import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
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

            // Skip empty lines and comments
            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=value
            if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/i', $line, $matches)) {
                $name = strtoupper($matches[1]);
                $value = $matches[2];

                // Remove surrounding quotes
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
