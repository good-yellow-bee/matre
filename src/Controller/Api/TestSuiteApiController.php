<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TestSuite;
use App\Repository\TestEnvironmentRepository;
use App\Repository\TestSuiteRepository;
use Cron\CronExpression;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/test-suites')]
#[IsGranted('ROLE_USER')]
class TestSuiteApiController extends AbstractController
{
    public function __construct(
        private readonly TestSuiteRepository $testSuiteRepository,
        private readonly TestEnvironmentRepository $environmentRepository,
        private readonly EntityManagerInterface $entityManager,
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

    #[Route('/types', name: 'api_test_suite_types', methods: ['GET'])]
    public function types(): JsonResponse
    {
        return $this->json(array_map(
            fn (string $value, string $label) => ['value' => $value, 'label' => $label],
            array_keys(TestSuite::TYPES),
            array_values(TestSuite::TYPES),
        ));
    }

    #[Route('/environments', name: 'api_test_suite_available_environments', methods: ['GET'])]
    public function availableEnvironments(): JsonResponse
    {
        $environments = $this->environmentRepository->findBy(['isActive' => true], ['name' => 'ASC']);

        return $this->json(array_map(
            fn ($env) => [
                'id' => $env->getId(),
                'name' => $env->getName(),
            ],
            $environments,
        ));
    }

    #[Route('/{id}', name: 'api_test_suite_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $suite = $this->testSuiteRepository->find($id);

        if (!$suite) {
            return $this->json(['error' => 'Test suite not found'], 404);
        }

        return $this->json([
            'id' => $suite->getId(),
            'name' => $suite->getName(),
            'type' => $suite->getType(),
            'testPattern' => $suite->getTestPattern(),
            'excludedTests' => $suite->getExcludedTests(),
            'description' => $suite->getDescription(),
            'cronExpression' => $suite->getCronExpression(),
            'isActive' => $suite->getIsActive(),
            'environments' => array_map(
                fn ($env) => $env->getId(),
                $suite->getEnvironments()->toArray(),
            ),
            'createdAt' => $suite->getCreatedAt()->format('c'),
            'updatedAt' => $suite->getUpdatedAt()?->format('c'),
        ]);
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

    #[Route('', name: 'api_test_suite_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateSuiteData($data);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        $suite = new TestSuite();
        $this->populateSuite($suite, $data);

        $this->entityManager->persist($suite);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Test suite created successfully',
            'id' => $suite->getId(),
        ], 201);
    }

    #[Route('/{id}', name: 'api_test_suite_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $suite = $this->testSuiteRepository->find($id);

        if (!$suite) {
            return $this->json(['error' => 'Test suite not found'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateSuiteData($data, $suite);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        $this->populateSuite($suite, $data);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Test suite updated successfully',
        ]);
    }

    #[Route('/validate-name', name: 'api_test_suite_validate_name', methods: ['POST'])]
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

        $qb = $this->testSuiteRepository->createQueryBuilder('s')
            ->where('s.name = :name')
            ->setParameter('name', $name);

        if ($excludeId) {
            $qb->andWhere('s.id != :id')
               ->setParameter('id', $excludeId);
        }

        $exists = null !== $qb->getQuery()->getOneOrNullResult();

        return $this->json([
            'valid' => !$exists,
            'message' => $exists ? 'A suite with this name already exists' : 'Name is available',
        ]);
    }

    #[Route('/validate-cron', name: 'api_test_suite_validate_cron', methods: ['POST'])]
    public function validateCron(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $expression = $data['cronExpression'] ?? '';

        if (empty($expression)) {
            return $this->json([
                'valid' => true,
                'message' => 'Cron expression is optional',
            ]);
        }

        try {
            $cron = new CronExpression($expression);
            $nextRun = $cron->getNextRunDate()->format('M j, Y H:i');

            return $this->json([
                'valid' => true,
                'message' => "Next run: {$nextRun}",
            ]);
        } catch (\InvalidArgumentException) {
            return $this->json([
                'valid' => false,
                'message' => 'Invalid cron expression',
            ]);
        }
    }

    /** @return array<string, string> */
    private function validateSuiteData(array $data, ?TestSuite $existing = null): array
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
            $qb = $this->testSuiteRepository->createQueryBuilder('s')
                ->where('s.name = :name')
                ->setParameter('name', $data['name']);

            if ($existing) {
                $qb->andWhere('s.id != :id')
                   ->setParameter('id', $existing->getId());
            }

            if (null !== $qb->getQuery()->getOneOrNullResult()) {
                $errors['name'] = 'A suite with this name already exists';
            }
        }

        // Validate type
        if (empty($data['type'])) {
            $errors['type'] = 'Type is required';
        } elseif (!array_key_exists($data['type'], TestSuite::TYPES)) {
            $errors['type'] = 'Invalid suite type';
        }

        // Validate testPattern
        if (empty($data['testPattern'])) {
            $errors['testPattern'] = 'Test pattern is required';
        }

        // Validate cronExpression (optional)
        if (!empty($data['cronExpression'])) {
            try {
                new CronExpression($data['cronExpression']);
            } catch (\InvalidArgumentException) {
                $errors['cronExpression'] = 'Invalid cron expression';
            }
        }

        if (array_key_exists('excludedTests', $data)) {
            if (null !== $data['excludedTests'] && !is_string($data['excludedTests'])) {
                $errors['excludedTests'] = 'Excluded tests must be a string';
            } elseif (is_string($data['excludedTests']) && mb_strlen(trim($data['excludedTests'])) > 5000) {
                $errors['excludedTests'] = 'Excluded tests cannot exceed 5000 characters';
            }
        }

        if (
            isset($data['type'])
            && TestSuite::TYPE_MFTF_GROUP !== $data['type']
            && !empty(trim((string) ($data['excludedTests'] ?? '')))
        ) {
            $errors['excludedTests'] = 'Excluded tests are supported only for MFTF Group suites';
        }

        return $errors;
    }

    private function populateSuite(TestSuite $suite, array $data): void
    {
        $suite->setName($data['name']);
        $suite->setType($data['type']);
        $suite->setTestPattern($data['testPattern']);
        $suite->setExcludedTests(TestSuite::TYPE_MFTF_GROUP === $data['type'] ? ($data['excludedTests'] ?? null) : null);
        $suite->setDescription($data['description'] ?? null);
        $suite->setCronExpression($data['cronExpression'] ?? null);
        $suite->setIsActive($data['isActive'] ?? true);

        // Handle environments
        $currentEnvironments = $suite->getEnvironments()->toArray();
        foreach ($currentEnvironments as $env) {
            $suite->removeEnvironment($env);
        }

        $environmentIds = $data['environments'] ?? [];
        if (!empty($environmentIds)) {
            $environments = $this->environmentRepository->findBy(['id' => $environmentIds]);
            foreach ($environments as $env) {
                $suite->addEnvironment($env);
            }
        }
    }
}
