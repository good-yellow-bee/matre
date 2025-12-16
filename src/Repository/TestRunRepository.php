<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TestEnvironment;
use App\Entity\TestRun;
use App\Entity\TestSuite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TestRun>
 */
class TestRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestRun::class);
    }

    public function save(TestRun $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TestRun $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all test runs ordered by creation date (newest first).
     *
     * @return TestRun[]
     */
    public function findAllOrdered(int $limit = 100): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find paginated test runs with eager-loaded relations.
     * Prevents N+1 queries on environment and suite.
     *
     * @param array<string, mixed> $criteria
     *
     * @return TestRun[]
     */
    public function findPaginatedWithRelations(
        array $criteria = [],
        int $limit = 20,
        int $offset = 0,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('e', 's')
            ->join('r.environment', 'e')
            ->leftJoin('r.suite', 's');

        foreach ($criteria as $field => $value) {
            if ($field === 'environment') {
                $qb->andWhere('e.id = :envId')->setParameter('envId', $value);
            } elseif ($field === 'status') {
                $qb->andWhere('r.status = :status')->setParameter('status', $value);
            } elseif ($field === 'type') {
                $qb->andWhere('r.type = :type')->setParameter('type', $value);
            }
        }

        return $qb
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find test runs by status.
     *
     * @return TestRun[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', $status)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find running test runs.
     *
     * @return TestRun[]
     */
    public function findRunning(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('statuses', [
                TestRun::STATUS_PREPARING,
                TestRun::STATUS_CLONING,
                TestRun::STATUS_RUNNING,
                TestRun::STATUS_REPORTING,
            ])
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending test runs.
     *
     * @return TestRun[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', TestRun::STATUS_PENDING)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find test runs by environment.
     *
     * @return TestRun[]
     */
    public function findByEnvironment(TestEnvironment $environment, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.environment = :environment')
            ->setParameter('environment', $environment)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find test runs by suite.
     *
     * @return TestRun[]
     */
    public function findBySuite(TestSuite $suite, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.suite = :suite')
            ->setParameter('suite', $suite)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent test runs (last N days).
     *
     * @return TestRun[]
     */
    public function findRecent(int $days = 7): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('r')
            ->andWhere('r.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find completed test runs older than specified days.
     *
     * @return TestRun[]
     */
    public function findOldCompleted(int $days = 30): array
    {
        $before = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('r')
            ->andWhere('r.completedAt IS NOT NULL')
            ->andWhere('r.completedAt < :before')
            ->setParameter('before', $before)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count test runs by status.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('r.status, COUNT(r.id) as count')
            ->groupBy('r.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Check if there are running tests for an environment.
     */
    public function hasRunningForEnvironment(TestEnvironment $environment): bool
    {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.environment = :environment')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('environment', $environment)
            ->setParameter('statuses', [
                TestRun::STATUS_PREPARING,
                TestRun::STATUS_CLONING,
                TestRun::STATUS_RUNNING,
                TestRun::STATUS_REPORTING,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Get statistics for dashboard.
     *
     * @return array{total: int, completed: int, failed: int, running: int, pending: int}
     */
    public function getStatistics(int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        $result = $this->createQueryBuilder('r')
            ->select('r.status, COUNT(r.id) as count')
            ->andWhere('r.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('r.status')
            ->getQuery()
            ->getResult();

        $stats = [
            'total' => 0,
            'completed' => 0,
            'failed' => 0,
            'running' => 0,
            'pending' => 0,
        ];

        foreach ($result as $row) {
            $count = (int) $row['count'];
            $stats['total'] += $count;

            match ($row['status']) {
                TestRun::STATUS_COMPLETED => $stats['completed'] += $count,
                TestRun::STATUS_FAILED, TestRun::STATUS_CANCELLED => $stats['failed'] += $count,
                TestRun::STATUS_PENDING => $stats['pending'] += $count,
                default => $stats['running'] += $count,
            };
        }

        return $stats;
    }
}
