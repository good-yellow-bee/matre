<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TestReport;
use App\Entity\TestRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TestReport>
 */
class TestReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestReport::class);
    }

    public function save(TestReport $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TestReport $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find reports for a test run.
     *
     * @return TestReport[]
     */
    public function findByTestRun(TestRun $testRun): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.testRun = :testRun')
            ->setParameter('testRun', $testRun)
            ->orderBy('r.generatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find Allure report for a test run.
     */
    public function findAllureByTestRun(TestRun $testRun): ?TestReport
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.testRun = :testRun')
            ->andWhere('r.reportType = :type')
            ->setParameter('testRun', $testRun)
            ->setParameter('type', TestReport::TYPE_ALLURE)
            ->orderBy('r.generatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find expired reports.
     *
     * @return TestReport[]
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.expiresAt IS NOT NULL')
            ->andWhere('r.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reports by type.
     *
     * @return TestReport[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.reportType = :type')
            ->setParameter('type', $type)
            ->orderBy('r.generatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent reports.
     *
     * @return TestReport[]
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.generatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete expired reports.
     */
    public function deleteExpired(): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.expiresAt IS NOT NULL')
            ->andWhere('r.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Count reports by type.
     *
     * @return array<string, int>
     */
    public function countByType(): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('r.reportType, COUNT(r.id) as count')
            ->groupBy('r.reportType')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['reportType']] = (int) $row['count'];
        }

        return $counts;
    }
}
