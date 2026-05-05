<?php

namespace App\Repository;

use App\Entity\Offer;
use App\Entity\Rating;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    /**
     * Get average stars for an offer (returns null if no ratings)
     */
    public function getAverageStars(Offer $offer): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.stars) as avg')
            ->where('r.offer = :offer')
            ->setParameter('offer', $offer)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round((float) $result, 1) : null;
    }

    /**
     * Count total ratings for an offer
     */
    public function countByOffer(Offer $offer): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.offer = :offer')
            ->setParameter('offer', $offer)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Check if a reservation already has a rating
     */
    public function findByReservation(Reservation $reservation): ?Rating
    {
        return $this->findOneBy(['reservation' => $reservation]);
    }

    /**
     * Get all ratings for an offer (for reviews section)
     */
    public function findByOffer(Offer $offer): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.offer = :offer')
            ->setParameter('offer', $offer)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get average stars for multiple offers at once (for index page)
     */
    public function getAverageStarsForOffers(array $offerIds): array
    {
        if (empty($offerIds)) return [];

        $results = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.offer) as offerId, AVG(r.stars) as avg, COUNT(r.id) as total')
            ->where('r.offer IN (:ids)')
            ->setParameter('ids', $offerIds)
            ->groupBy('r.offer')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($results as $row) {
            $map[$row['offerId']] = [
                'avg'   => round((float) $row['avg'], 1),
                'total' => (int) $row['total'],
            ];
        }

        return $map;
    }
}