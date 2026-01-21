<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CronJob;
use App\Message\CronJobMessage;
use App\Repository\CronJobRepository;
use Cron\CronExpression;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/cron-jobs')]
#[IsGranted('ROLE_ADMIN')]
class CronJobApiController extends AbstractController
{
    public function __construct(
        private readonly CronJobRepository $cronJobRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly KernelInterface $kernel,
    ) {
    }

    #[Route('/list', name: 'api_cron_jobs_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $sort = $request->query->get('sort', 'name');
        $order = $request->query->get('order', 'asc');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = max(1, min(100, (int) $request->query->get('perPage', 20)));

        $validSorts = ['id', 'name', 'command', 'cronExpression', 'isActive', 'lastStatus', 'lastRunAt', 'createdAt'];
        if (!in_array($sort, $validSorts, true)) {
            $sort = 'name';
        }

        $order = 'DESC' === strtoupper($order) ? 'DESC' : 'ASC';

        $qb = $this->cronJobRepository->createQueryBuilder('c')
            ->orderBy('c.' . $sort, $order);

        if ('' !== $search) {
            $qb
                ->andWhere('LOWER(c.name) LIKE :search OR LOWER(c.command) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        // Count total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        // Paginate
        $results = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(static fn (CronJob $job) => [
            'id' => $job->getId(),
            'name' => $job->getName(),
            'description' => $job->getDescription(),
            'command' => $job->getCommand(),
            'cronExpression' => $job->getCronExpression(),
            'isActive' => $job->getIsActive(),
            'lastStatus' => $job->getLastStatus(),
            'lastRunAt' => $job->getLastRunAt()?->format('c'),
            'createdAt' => $job->getCreatedAt()->format('c'),
        ], $results);

        return $this->json([
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    #[Route('/{id}', name: 'api_cron_jobs_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $job = $this->cronJobRepository->find($id);

        if (!$job) {
            return $this->json(['error' => 'Cron job not found'], 404);
        }

        return $this->json([
            'id' => $job->getId(),
            'name' => $job->getName(),
            'description' => $job->getDescription(),
            'command' => $job->getCommand(),
            'cronExpression' => $job->getCronExpression(),
            'isActive' => $job->getIsActive(),
            'lastStatus' => $job->getLastStatus(),
            'lastOutput' => $job->getLastOutput(),
            'lastRunAt' => $job->getLastRunAt()?->format('c'),
            'createdAt' => $job->getCreatedAt()->format('c'),
            'updatedAt' => $job->getUpdatedAt()?->format('c'),
        ]);
    }

    #[Route('', name: 'api_cron_jobs_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateCronJobData($data);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        $job = new CronJob();
        $job->setName($data['name']);
        $job->setDescription($data['description'] ?? null);
        $job->setCommand($data['command']);
        $job->setCronExpression($data['cronExpression']);
        $job->setIsActive($data['isActive'] ?? true);

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Cron job created successfully',
            'id' => $job->getId(),
        ], 201);
    }

    #[Route('/{id}', name: 'api_cron_jobs_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $job = $this->cronJobRepository->find($id);

        if (!$job) {
            return $this->json(['error' => 'Cron job not found'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateCronJobData($data, $job);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        $job->setName($data['name']);
        $job->setDescription($data['description'] ?? null);
        $job->setCommand($data['command']);
        $job->setCronExpression($data['cronExpression']);
        $job->setIsActive($data['isActive'] ?? true);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Cron job updated successfully',
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'api_cron_jobs_toggle_active', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleActive(int $id, Request $request): JsonResponse
    {
        $job = $this->cronJobRepository->find($id);

        if (!$job) {
            return $this->json(['error' => 'Cron job not found'], 404);
        }

        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('cron_job_api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $job->setIsActive(!$job->getIsActive());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'isActive' => $job->getIsActive(),
            'message' => sprintf('Cron job "%s" %s', $job->getName(), $job->getIsActive() ? 'activated' : 'deactivated'),
        ]);
    }

    #[Route('/{id}/run', name: 'api_cron_jobs_run', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function run(int $id, Request $request): JsonResponse
    {
        $job = $this->cronJobRepository->find($id);

        if (!$job) {
            return $this->json(['error' => 'Cron job not found'], 404);
        }

        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('cron_job_api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $this->messageBus->dispatch(new CronJobMessage($job->getId()));

        return $this->json([
            'success' => true,
            'message' => sprintf('Cron job "%s" has been triggered', $job->getName()),
        ]);
    }

    #[Route('/{id}', name: 'api_cron_jobs_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        $job = $this->cronJobRepository->find($id);

        if (!$job) {
            return $this->json(['error' => 'Cron job not found'], 404);
        }

        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('cron_job_api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $name = $job->getName();
        $this->entityManager->remove($job);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => sprintf('Cron job "%s" has been deleted', $name),
        ]);
    }

    #[Route('/commands', name: 'api_cron_jobs_commands', methods: ['GET'])]
    public function getCommands(): JsonResponse
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $commands = [];
        foreach ($application->all() as $name => $command) {
            // Skip hidden and internal commands
            if ($command->isHidden() || str_starts_with($name, '_')) {
                continue;
            }

            $commands[] = [
                'name' => $name,
                'description' => $command->getDescription(),
            ];
        }

        // Sort by name
        usort($commands, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $this->json($commands);
    }

    #[Route('/validate-name', name: 'api_cron_jobs_validate_name', methods: ['POST'])]
    public function validateName(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $name = $data['name'] ?? '';
        $excludeId = $data['excludeId'] ?? null;

        if (strlen($name) < 3) {
            return $this->json([
                'valid' => false,
                'message' => 'Name must be at least 3 characters',
            ]);
        }

        $qb = $this->cronJobRepository->createQueryBuilder('c')
            ->where('c.name = :name')
            ->setParameter('name', $name);

        if ($excludeId) {
            $qb->andWhere('c.id != :id')
               ->setParameter('id', $excludeId);
        }

        $exists = null !== $qb->getQuery()->getOneOrNullResult();

        return $this->json([
            'valid' => !$exists,
            'message' => $exists ? 'A cron job with this name already exists' : 'Name is available',
        ]);
    }

    #[Route('/validate-cron', name: 'api_cron_jobs_validate_cron', methods: ['POST'])]
    public function validateCron(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $expression = $data['expression'] ?? '';

        if (empty($expression)) {
            return $this->json([
                'valid' => false,
                'message' => 'Cron expression is required',
            ]);
        }

        try {
            $cron = new CronExpression($expression);
            $nextRun = $cron->getNextRunDate()->format('Y-m-d H:i:s');

            return $this->json([
                'valid' => true,
                'message' => "Valid. Next run: $nextRun",
                'nextRun' => $nextRun,
            ]);
        } catch (\InvalidArgumentException) {
            return $this->json([
                'valid' => false,
                'message' => 'Invalid cron expression',
            ]);
        }
    }

    /** @return array<string, string> */
    private function validateCronJobData(array $data, ?CronJob $existing = null): array
    {
        $errors = [];

        // Validate name
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) < 3) {
            $errors['name'] = 'Name must be at least 3 characters';
        } elseif (strlen($data['name']) > 100) {
            $errors['name'] = 'Name cannot exceed 100 characters';
        } else {
            // Check uniqueness
            $qb = $this->cronJobRepository->createQueryBuilder('c')
                ->where('c.name = :name')
                ->setParameter('name', $data['name']);

            if ($existing) {
                $qb->andWhere('c.id != :id')
                   ->setParameter('id', $existing->getId());
            }

            if (null !== $qb->getQuery()->getOneOrNullResult()) {
                $errors['name'] = 'A cron job with this name already exists';
            }
        }

        // Validate command
        if (empty($data['command'])) {
            $errors['command'] = 'Command is required';
        } elseif (strlen($data['command']) > 255) {
            $errors['command'] = 'Command cannot exceed 255 characters';
        } else {
            // Validate command exists
            $application = new Application($this->kernel);
            $application->setAutoExit(false);
            $commandName = explode(' ', trim($data['command']), 2)[0];

            try {
                $application->find($commandName);
            } catch (\Symfony\Component\Console\Exception\CommandNotFoundException) {
                $errors['command'] = sprintf('Command "%s" not found', $commandName);
            }
        }

        // Validate cron expression
        if (empty($data['cronExpression'])) {
            $errors['cronExpression'] = 'Cron expression is required';
        } else {
            try {
                new CronExpression($data['cronExpression']);
            } catch (\InvalidArgumentException) {
                $errors['cronExpression'] = 'Invalid cron expression';
            }
        }

        return $errors;
    }
}
