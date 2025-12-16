<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GlobalEnvVariable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @extends ServiceEntityRepository<GlobalEnvVariable>
 */
class GlobalEnvVariableRepository extends ServiceEntityRepository
{
    private const CACHE_KEY = 'global_env_vars_kv';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        ManagerRegistry $registry,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct($registry, GlobalEnvVariable::class);
    }

    public function save(GlobalEnvVariable $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
            $this->invalidateCache();
        }
    }

    public function remove(GlobalEnvVariable $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
            $this->invalidateCache();
        }
    }

    /**
     * Invalidate the key-value cache after modifications.
     */
    public function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
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
     * Results are cached for 1 hour to reduce DB queries during test runs.
     *
     * @return array<string, string>
     */
    public function getAllAsKeyValue(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            $variables = $this->findAllOrdered();
            $result = [];

            foreach ($variables as $var) {
                $result[$var->getName()] = $var->getValue();
            }

            return $result;
        });
    }
}
