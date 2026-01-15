<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TestResult;
use App\Entity\TestRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TestResult>
 */
class TestResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestResult::class);
    }

    public function save(TestResult $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TestResult $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find results for a test run.
     *
     * @return TestResult[]
     */
    public function findByTestRun(TestRun $testRun): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.testRun = :testRun')
            ->setParameter('testRun', $testRun)
            ->orderBy('r.testName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find failed results for a test run.
     *
     * @return TestResult[]
     */
    public function findFailedByTestRun(TestRun $testRun): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.testRun = :testRun')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('testRun', $testRun)
            ->setParameter('statuses', [TestResult::STATUS_FAILED, TestResult::STATUS_BROKEN])
            ->orderBy('r.testName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find results by status.
     *
     * @return TestResult[]
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
     * Find results by test name pattern.
     *
     * @return TestResult[]
     */
    public function findByTestNamePattern(string $pattern): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.testName LIKE :pattern')
            ->setParameter('pattern', '%'.$pattern.'%')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count results by status for a test run.
     *
     * @return array<string, int>
     */
    public function countByStatusForTestRun(TestRun $testRun): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('r.status, COUNT(r.id) as count')
            ->andWhere('r.testRun = :testRun')
            ->setParameter('testRun', $testRun)
            ->groupBy('r.status')
            ->getQuery()
            ->getResult();

        $counts = [
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'broken' => 0,
        ];

        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Delete results for a test run.
     */
    public function deleteByTestRun(TestRun $testRun): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.testRun = :testRun')
            ->setParameter('testRun', $testRun)
            ->getQuery()
            ->execute();
    }

    /**
     * Get average duration by test name.
     *
     * @return array<string, float>
     */
    public function getAverageDurationByTest(int $limit = 50): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('r.testName, AVG(r.duration) as avgDuration')
            ->andWhere('r.duration IS NOT NULL')
            ->groupBy('r.testName')
            ->orderBy('avgDuration', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $averages = [];
        foreach ($result as $row) {
            $averages[$row['testName']] = (float) $row['avgDuration'];
        }

        return $averages;
    }

    /**
     * Find test execution history across runs for a specific testId and environment.
     * Returns the last N results ordered by test run start time (newest first).
     *
     * @return TestResult[]
     */
    public function findHistoryByTestId(string $testId, int $environmentId, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.testRun', 'tr')
            ->andWhere('r.testId = :testId')
            ->andWhere('tr.environment = :environmentId')
            ->andWhere('tr.status IN (:completedStatuses)')
            ->setParameter('testId', $testId)
            ->setParameter('environmentId', $environmentId)
            ->setParameter('completedStatuses', [TestRun::STATUS_COMPLETED, TestRun::STATUS_FAILED])
            ->orderBy('tr.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Batch get result counts for multiple test runs.
     * Returns array keyed by testRun ID with counts.
     *
     * @param int[] $testRunIds
     *
     * @return array<int, array{passed: int, failed: int, skipped: int, broken: int, total: int}>
     */
    public function getResultCountsForRuns(array $testRunIds): array
    {
        if (empty($testRunIds)) {
            return [];
        }

        $result = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.testRun) as runId, r.status, COUNT(r.id) as count')
            ->andWhere('r.testRun IN (:runIds)')
            ->setParameter('runIds', $testRunIds)
            ->groupBy('r.testRun, r.status')
            ->getQuery()
            ->getResult();

        // Initialize all runs with zero counts
        $counts = [];
        foreach ($testRunIds as $id) {
            $counts[$id] = [
                'passed' => 0,
                'failed' => 0,
                'skipped' => 0,
                'broken' => 0,
                'total' => 0,
            ];
        }

        // Fill in actual counts
        foreach ($result as $row) {
            $runId = (int) $row['runId'];
            $status = $row['status'];
            $count = (int) $row['count'];

            if (isset($counts[$runId][$status])) {
                $counts[$runId][$status] = $count;
            }
            $counts[$runId]['total'] += $count;
        }

        return $counts;
    }

    /**
     * Find all distinct test IDs.
     *
     * @return string[]
     */
    public function findDistinctTestIds(): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('DISTINCT r.testId')
            ->andWhere('r.testId IS NOT NULL')
            ->andWhere('r.testId != :empty')
            ->setParameter('empty', '')
            ->orderBy('r.testId', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'testId');
    }
}
