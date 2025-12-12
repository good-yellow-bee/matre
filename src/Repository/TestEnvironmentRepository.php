<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TestEnvironment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TestEnvironment>
 */
class TestEnvironmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestEnvironment::class);
    }

    public function save(TestEnvironment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TestEnvironment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all active environments.
     *
     * @return TestEnvironment[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('e.code', 'ASC')
            ->addOrderBy('e.region', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all environments ordered by code and region.
     *
     * @return TestEnvironment[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.code', 'ASC')
            ->addOrderBy('e.region', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find environment by name.
     */
    public function findByName(string $name): ?TestEnvironment
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find environments by code (e.g., 'dev', 'stage').
     *
     * @return TestEnvironment[]
     */
    public function findByCode(string $code): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.code = :code')
            ->setParameter('code', $code)
            ->orderBy('e.region', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find environment by code and region.
     */
    public function findByCodeAndRegion(string $code, string $region): ?TestEnvironment
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.code = :code')
            ->andWhere('e.region = :region')
            ->setParameter('code', $code)
            ->setParameter('region', $region)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
