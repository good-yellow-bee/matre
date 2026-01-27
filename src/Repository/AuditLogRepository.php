<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Find paginated audit logs with filters.
     *
     * @return AuditLog[]
     */
    public function findPaginated(
        array $filters = [],
        int $limit = 20,
        int $offset = 0,
        string $sortField = 'createdAt',
        string $sortOrder = 'DESC',
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u');

        $this->applyFilters($qb, $filters);

        $allowedSortFields = ['createdAt', 'entityType', 'action'];
        if (!in_array($sortField, $allowedSortFields, true)) {
            $sortField = 'createdAt';
        }
        $sortOrder = 'ASC' === strtoupper($sortOrder) ? 'ASC' : 'DESC';

        return $qb
            ->orderBy('a.' . $sortField, $sortOrder)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count logs matching filters.
     */
    public function countFiltered(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        $this->applyFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count logs older than cutoff date.
     */
    public function countOlderThan(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Delete logs older than retention period.
     */
    public function deleteOlderThan(\DateTimeImmutable $cutoff): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }

    /**
     * Get distinct entity types for filter dropdown.
     *
     * @return string[]
     */
    public function getDistinctEntityTypes(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('DISTINCT a.entityType')
            ->orderBy('a.entityType', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $result;
    }

    /**
     * Find logs for a specific entity.
     *
     * @return AuditLog[]
     */
    public function findByEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->where('a.entityType = :type')
            ->andWhere('a.entityId = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function applyFilters($qb, array $filters): void
    {
        if (!empty($filters['entityType'])) {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $filters['entityType']);
        }

        if (!empty($filters['action'])) {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $filters['action']);
        }

        if (!empty($filters['userId'])) {
            $qb->andWhere('a.user = :userId')
                ->setParameter('userId', $filters['userId']);
        }

        if (!empty($filters['dateFrom'])) {
            try {
                $dateFrom = new \DateTimeImmutable($filters['dateFrom']);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf('Invalid dateFrom format: %s', $filters['dateFrom']), 0, $e);
            }
            $qb->andWhere('a.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom->setTime(0, 0, 0));
        }

        if (!empty($filters['dateTo'])) {
            try {
                $dateTo = new \DateTimeImmutable($filters['dateTo']);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf('Invalid dateTo format: %s', $filters['dateTo']), 0, $e);
            }
            $qb->andWhere('a.createdAt <= :dateTo')
                ->setParameter('dateTo', $dateTo->setTime(23, 59, 59));
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('a.entityLabel LIKE :search OR a.entityType LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }
    }
}
