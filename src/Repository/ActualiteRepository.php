<?php

namespace App\Repository;

use App\Entity\Actualite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActualiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Actualite::class);
    }

    /**
     * Get all currently active banners for the public index page.
     */
    public function findActiveBanners(): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('a')
            ->where('a.isActive = true')
            ->andWhere('a.endsAt IS NULL OR a.endsAt > :now')
            ->setParameter('now', $now)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get banners for a specific agency.
     */
    public function findByAgency(User $agency): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.agence = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}