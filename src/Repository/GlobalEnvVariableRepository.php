<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GlobalEnvVariable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GlobalEnvVariable>
 */
class GlobalEnvVariableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GlobalEnvVariable::class);
    }

    public function save(GlobalEnvVariable $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GlobalEnvVariable $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all variables ordered by name.
     *
     * @return GlobalEnvVariable[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('v')
            ->orderBy('v.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find variable by name.
     */
    public function findByName(string $name): ?GlobalEnvVariable
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all variables as key-value array for merging.
     *
     * @return array<string, string>
     */
    public function getAllAsKeyValue(): array
    {
        $variables = $this->findAllOrdered();
        $result = [];

        foreach ($variables as $var) {
            $result[$var->getName()] = $var->getValue();
        }

        return $result;
    }
}
