<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationTemplate>
 */
class NotificationTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationTemplate::class);
    }

    public function save(NotificationTemplate $template, bool $flush = false): void
    {
        $this->getEntityManager()->persist($template);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NotificationTemplate $template, bool $flush = false): void
    {
        $this->getEntityManager()->remove($template);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find template by channel and event name.
     */
    public function findByChannelAndName(string $channel, string $name): ?NotificationTemplate
    {
        return $this->findOneBy(['channel' => $channel, 'name' => $name]);
    }

    /**
     * Find active template by channel and event name.
     */
    public function findActiveByChannelAndName(string $channel, string $name): ?NotificationTemplate
    {
        return $this->findOneBy([
            'channel' => $channel,
            'name' => $name,
            'isActive' => true,
        ]);
    }

    /**
     * Get all templates grouped by channel.
     *
     * @return array<string, NotificationTemplate[]>
     */
    public function findAllGroupedByChannel(): array
    {
        $templates = $this->createQueryBuilder('t')
            ->orderBy('t.channel', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [
            NotificationTemplate::CHANNEL_SLACK => [],
            NotificationTemplate::CHANNEL_EMAIL => [],
        ];

        foreach ($templates as $template) {
            $grouped[$template->getChannel()][] = $template;
        }

        return $grouped;
    }

    /**
     * Get all templates as flat array.
     *
     * @return NotificationTemplate[]
     */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.channel', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
