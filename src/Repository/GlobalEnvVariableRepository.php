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
     * Invalidate all env variable caches after modifications.
     */
    public function invalidateCache(): void
    {
        // Clear global cache
        $this->cache->delete(self::CACHE_KEY . '_global');

        // Clear all known environment caches
        try {
            foreach ($this->getDistinctEnvironments() as $env) {
                $this->cache->delete(self::CACHE_KEY . '_' . $env);
            }
        } catch (\Throwable) {
            // Ignore cache clearing errors
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
     * Find variable by name (first match).
     */
    public function findByName(string $name): ?GlobalEnvVariable
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.name = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find variable by name and value (for merge during import).
     * Returns the variable if same name+value exists, so we can add environment to it.
     */
    public function findByNameAndValue(string $name, string $value): ?GlobalEnvVariable
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.name = :name')
            ->andWhere('v.value = :value')
            ->setParameter('name', $name)
            ->setParameter('value', $value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all variables with the same name.
     *
     * @return GlobalEnvVariable[]
     */
    public function findAllByName(string $name): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.name = :name')
            ->setParameter('name', $name)
            ->orderBy('v.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all distinct environment names used in variables.
     * Extracts unique values from all JSON arrays.
     *
     * @return string[]
     */
    public function getDistinctEnvironments(): array
    {
        // Get all variables with non-null environments
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT DISTINCT JSON_UNQUOTE(env.value) as env_name
                FROM matre_global_env_variables v,
                     JSON_TABLE(v.environments, '$[*]' COLUMNS (value VARCHAR(50) PATH '$')) AS env
                WHERE v.environments IS NOT NULL
                ORDER BY env_name ASC";

        $result = $conn->executeQuery($sql)->fetchFirstColumn();

        return array_filter($result, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Get all variables as key-value array for merging.
     * Results are cached for 1 hour to reduce DB queries during test runs.
     *
     * If environment is specified:
     * 1. Fetch global vars (environments IS NULL or empty)
     * 2. Fetch vars where environments JSON array contains the target env
     * 3. Merge (env-specific overrides global for same name)
     *
     * @return array<string, string>
     */
    public function getAllAsKeyValue(?string $environment = null): array
    {
        $cacheKey = self::CACHE_KEY . ($environment ? '_' . $environment : '_global');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($environment): array {
            $item->expiresAfter(self::CACHE_TTL);

            $result = [];

            // Get global variables (environments IS NULL = applies to all)
            $globalVars = $this->createQueryBuilder('v')
                ->where('v.environments IS NULL')
                ->orderBy('v.name', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($globalVars as $var) {
                $result[$var->getName()] = $var->getValue();
            }

            // If environment specified, get env-specific vars and merge (override)
            if ($environment !== null) {
                // Use native SQL for JSON_CONTAINS
                $conn = $this->getEntityManager()->getConnection();
                $sql = "SELECT name, value FROM matre_global_env_variables
                        WHERE environments IS NOT NULL
                        AND JSON_CONTAINS(environments, :env)
                        ORDER BY name ASC";

                $envVars = $conn->executeQuery($sql, ['env' => json_encode($environment)])->fetchAllAssociative();

                foreach ($envVars as $var) {
                    $result[$var['name']] = $var['value'];
                }
            }

            return $result;
        });
    }
}
