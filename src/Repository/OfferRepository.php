<?php

namespace App\Repository;

use App\Entity\Offer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

class OfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offer::class);
    }

    /**
     * Returns Query for paginator — active offers with filters.
     */
    public function findActiveWithFiltersQuery(array $filters): Query
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->setParameter('status', 'ACTIVE');

        if (!empty($filters['q'])) {
            $qb->andWhere('o.title LIKE :q OR o.description LIKE :q OR o.location LIKE :q')
               ->setParameter('q', '%' . $filters['q'] . '%');
        }

        if (!empty($filters['location'])) {
            $qb->andWhere('o.location LIKE :location')
               ->setParameter('location', '%' . $filters['location'] . '%');
        }

        if (!empty($filters['minPrice'])) {
            $qb->andWhere('o.promoPrice >= :minPrice')
               ->setParameter('minPrice', $filters['minPrice']);
        }

        if (!empty($filters['maxPrice'])) {
            $qb->andWhere('o.promoPrice <= :maxPrice')
               ->setParameter('maxPrice', $filters['maxPrice']);
        }

        return $qb->orderBy('o.createdAt', 'DESC')->getQuery();
    }

    /**
     * Returns Query for paginator — active offers by agency.
     */
    public function findActiveByAgencyQuery(User $user): Query
    {
        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'ACTIVE')
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery();
    }

    /**
     * Returns Query for paginator — all offers with admin filters.
     */
    public function findAllWithFiltersQuery(array $filters): Query
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

        return $qb->orderBy('o.createdAt', 'DESC')->getQuery();
    }

    /**
     * Keep original array methods for places that don't need pagination.
     */
    public function findActiveWithFilters(array $filters): array
    {
        return $this->findActiveWithFiltersQuery($filters)->getResult();
    }

    public function findAllWithFilters(array $filters): array
    {
        return $this->findAllWithFiltersQuery($filters)->getResult();
    }


    /**
     * Get top destinations by number of confirmed reservations.
     */
    public function findTopDestinations(int $limit = 6): array
    {
        return $this->createQueryBuilder('o')
            ->select('o.location, COUNT(r.id) as reservationCount, MIN(o.promoPrice) as minPrice, o.imageUrl')
            ->join('o.reservations', 'r')
            ->where('r.status = :status')
            ->andWhere('o.status = :offerStatus')
            ->andWhere('o.location IS NOT NULL')
            ->andWhere('o.location != :empty')
            ->setParameter('status', 'CONFIRMED')
            ->setParameter('offerStatus', 'ACTIVE')
            ->setParameter('empty', '')
            ->groupBy('o.location, o.imageUrl')
            ->orderBy('reservationCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }}