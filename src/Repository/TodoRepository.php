<?php

namespace App\Repository;

use App\Entity\Todo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Todo>
 */
class TodoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Todo::class);
    }

    /**
     * Find todos by user
     * @return Todo[]
     */
    public function findByUser($user, bool $onlyIncomplete = false): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        if ($onlyIncomplete) {
            $qb->andWhere('t.isCompleted = :completed')
               ->setParameter('completed', false);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find completed todos by user
     * @return Todo[]
     */
    public function findCompletedByUser($user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.isCompleted = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', true)
            ->orderBy('t.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find high priority todos
     * @return Todo[]
     */
    public function findHighPriorityByUser($user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.priority = :priority')
            ->andWhere('t.isCompleted = :completed')
            ->setParameter('user', $user)
            ->setParameter('priority', 2) // 2 = High priority
            ->setParameter('completed', false)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find overdue todos
     * @return Todo[]
     */
    public function findOverdueByUser($user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.dueDate < :now')
            ->andWhere('t.isCompleted = :completed')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('completed', false)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}