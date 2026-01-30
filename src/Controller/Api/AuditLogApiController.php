<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/audit-logs')]
#[IsGranted('ROLE_ADMIN')]
class AuditLogApiController extends AbstractController
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('/list', name: 'api_audit_logs_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'entityType' => $request->query->get('entityType'),
            'action' => $request->query->get('action'),
            'userId' => $request->query->get('userId'),
            'dateFrom' => $request->query->get('dateFrom'),
            'dateTo' => $request->query->get('dateTo'),
            'search' => $request->query->get('search'),
        ];

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = max(1, min(100, (int) $request->query->get('perPage', 20)));
        $sort = $request->query->get('sort', 'createdAt');
        $order = 'ASC' === strtoupper($request->query->get('order', 'DESC')) ? 'ASC' : 'DESC';

        $logs = $this->auditLogRepository->findPaginated(
            $filters,
            $perPage,
            ($page - 1) * $perPage,
            $sort,
            $order,
        );

        $total = $this->auditLogRepository->countFiltered($filters);

        $data = array_map(fn (AuditLog $log) => [
            'id' => $log->getId(),
            'entityType' => $log->getEntityType(),
            'entityId' => $log->getEntityId(),
            'entityLabel' => $log->getEntityLabel(),
            'action' => $log->getAction(),
            'oldData' => $log->getOldData(),
            'newData' => $log->getNewData(),
            'changedFields' => $log->getChangedFields(),
            'user' => $log->getUser() ? [
                'id' => $log->getUser()->getId(),
                'username' => $log->getUser()->getUsername(),
            ] : null,
            'ipAddress' => $log->getIpAddress(),
            'createdAt' => $log->getCreatedAt()->format('c'),
        ], $logs);

        return $this->json([
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    #[Route('/filters', name: 'api_audit_logs_filters', methods: ['GET'])]
    public function filters(): JsonResponse
    {
        return $this->json([
            'entityTypes' => $this->auditLogRepository->getDistinctEntityTypes(),
            'actions' => [
                AuditLog::ACTION_CREATE,
                AuditLog::ACTION_UPDATE,
                AuditLog::ACTION_DELETE,
            ],
            'users' => array_map(fn ($u) => [
                'id' => $u->getId(),
                'username' => $u->getUsername(),
            ], $this->userRepository->findAll()),
        ]);
    }

    #[Route('/{id}', name: 'api_audit_logs_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $log = $this->auditLogRepository->find($id);

        if (!$log) {
            return $this->json(['error' => 'Audit log not found'], 404);
        }

        return $this->json([
            'id' => $log->getId(),
            'entityType' => $log->getEntityType(),
            'entityId' => $log->getEntityId(),
            'entityLabel' => $log->getEntityLabel(),
            'action' => $log->getAction(),
            'oldData' => $log->getOldData(),
            'newData' => $log->getNewData(),
            'changedFields' => $log->getChangedFields(),
            'user' => $log->getUser() ? [
                'id' => $log->getUser()->getId(),
                'username' => $log->getUser()->getUsername(),
            ] : null,
            'ipAddress' => $log->getIpAddress(),
            'createdAt' => $log->getCreatedAt()->format('c'),
        ]);
    }

    #[Route('/entity/{entityType}/{entityId}', name: 'api_audit_logs_by_entity', methods: ['GET'], requirements: ['entityId' => '\d+'])]
    public function byEntity(string $entityType, int $entityId): JsonResponse
    {
        $logs = $this->auditLogRepository->findByEntity($entityType, $entityId);

        $data = array_map(fn (AuditLog $log) => [
            'id' => $log->getId(),
            'action' => $log->getAction(),
            'oldData' => $log->getOldData(),
            'newData' => $log->getNewData(),
            'changedFields' => $log->getChangedFields(),
            'user' => $log->getUser() ? [
                'id' => $log->getUser()->getId(),
                'username' => $log->getUser()->getUsername(),
            ] : null,
            'ipAddress' => $log->getIpAddress(),
            'createdAt' => $log->getCreatedAt()->format('c'),
        ], $logs);

        return $this->json([
            'data' => $data,
            'total' => count($data),
        ]);
    }
}
