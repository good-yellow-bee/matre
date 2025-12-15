<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TestSuite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TestSuite>
 */
class TestSuiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestSuite::class);
    }

    public function save(TestSuite $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TestSuite $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all active test suites.
     *
     * @return TestSuite[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all test suites ordered by name.
     *
     * @return TestSuite[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find test suite by name.
     */
    public function findByName(string $name): ?TestSuite
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all scheduled (active with cron expression) test suites.
     *
     * @return TestSuite[]
     */
    public function findScheduled(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isActive = :active')
            ->andWhere('s.cronExpression IS NOT NULL')
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find test suites by type.
     *
     * @return TestSuite[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.type = :type')
            ->setParameter('type', $type)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find MFTF test suites.
     *
     * @return TestSuite[]
     */
    public function findMftfSuites(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.type IN (:types)')
            ->setParameter('types', [TestSuite::TYPE_MFTF_GROUP, TestSuite::TYPE_MFTF_TEST])
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find Playwright test suites.
     *
     * @return TestSuite[]
     */
    public function findPlaywrightSuites(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.type IN (:types)')
            ->setParameter('types', [TestSuite::TYPE_PLAYWRIGHT_GROUP, TestSuite::TYPE_PLAYWRIGHT_TEST])
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Generate a unique copy name with auto-increment.
     * "Suite A" → "Suite A (Copy)" → "Suite A (Copy 2)" → etc.
     */
    public function findNextAvailableCopyName(string $baseName): string
    {
        $name = $baseName . ' (Copy)';
        if (!$this->findByName($name)) {
            return $name;
        }

        $i = 2;
        while ($this->findByName($baseName . " (Copy $i)")) {
            ++$i;
        }

        return $baseName . " (Copy $i)";
    }
}
