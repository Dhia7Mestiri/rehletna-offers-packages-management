<?php

namespace App\Service;

use App\Entity\Todo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class TodoService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function createTodo(User $user, string $title, ?string $description = null, int $priority = 0): Todo
    {
        $todo = new Todo();
        $todo->setUser($user);
        $todo->setTitle($title);
        $todo->setDescription($description);
        $todo->setPriority($priority);

        $this->entityManager->persist($todo);
        $this->entityManager->flush();

        return $todo;
    }

    public function completeTodo(Todo $todo): void
    {
        $todo->setIsCompleted(true);
        $this->entityManager->flush();
    }

    public function getUserTodos(User $user, bool $onlyIncomplete = false): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Todo::class, 't')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        if ($onlyIncomplete) {
            $qb->andWhere('t.isCompleted = :completed')
               ->setParameter('completed', false);
        }

        return $qb->getQuery()->getResult();
    }
}