<?php

namespace App\Repository;

use App\Entity\Offer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offer::class);
    }

    public function findActiveWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.offerServices', 'os')
            ->leftJoin('os.service', 's')
            ->andWhere('o.status = :status')
            ->setParameter('status', 'ACTIVE')
            ->orderBy('o.createdAt', 'DESC')
            ->distinct();

        if (!empty($filters['q'])) {
            $qb->andWhere('LOWER(o.title) LIKE LOWER(:q) OR LOWER(o.description) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $filters['q'] . '%');
        }

        if (!empty($filters['location'])) {
            $qb->andWhere('LOWER(o.location) LIKE LOWER(:location)')
                ->setParameter('location', '%' . $filters['location'] . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('s.type = :type')
                ->setParameter('type', strtoupper($filters['type']));
        }

        if ($filters['minPrice'] !== '') {
            $qb->andWhere('o.promoPrice >= :minPrice')
                ->setParameter('minPrice', $filters['minPrice']);
        }

        if ($filters['maxPrice'] !== '') {
            $qb->andWhere('o.promoPrice <= :maxPrice')
                ->setParameter('maxPrice', $filters['maxPrice']);
        }

        return $qb->getQuery()->getResult();
    }
    public function findAllWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.user', 'u');
 
        if (!empty($filters['q'])) {
            $qb->andWhere('o.title LIKE :q OR o.description LIKE :q OR o.location LIKE :q')
               ->setParameter('q', '%' . $filters['q'] . '%');
        }
 
        if (!empty($filters['status'])) {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', strtoupper($filters['status']));
        }
 
        if (!empty($filters['agency'])) {
            $qb->andWhere('u.id = :agency')
               ->setParameter('agency', $filters['agency']);
        }
 
        return $qb->orderBy('o.createdAt', 'DESC')->getQuery()->getResult();
    }
}