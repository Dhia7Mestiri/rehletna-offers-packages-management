<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findByAgency(User $agencyUser): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.offer', 'o')
            ->where('o.user = :agency')
            ->setParameter('agency', $agencyUser)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Monthly revenue for last N months — done in PHP to avoid
     * DQL YEAR()/MONTH() compatibility issues across drivers.
     */
    public function getMonthlyRevenueByAgency(User $agencyUser, int $months = 6): array
    {
        $start = new \DateTimeImmutable("-{$months} months midnight first day of this month");

        /** @var Reservation[] $rows */
        $rows = $this->createQueryBuilder('r')
            ->join('r.offer', 'o')
            ->where('o.user = :agency')
            ->andWhere('r.status = :status')
            ->andWhere('r.createdAt >= :start')
            ->setParameter('agency', $agencyUser)
            ->setParameter('status', 'CONFIRMED')
            ->setParameter('start', $start)
            ->getQuery()
            ->getResult();

        // Group by year-month in PHP
        $grouped = [];
        foreach ($rows as $r) {
            $key = $r->getCreatedAt()->format('Y-n'); // e.g. "2026-4"
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['revenue' => 0.0, 'bookings' => 0];
            }
            $grouped[$key]['revenue']  += (float) $r->getTotalAmount();
            $grouped[$key]['bookings'] += 1;
        }

        // Build full month range (fill gaps with zeros)
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = new \DateTimeImmutable("-{$i} months");
            $key   = $date->format('Y-n');
            $label = $date->format('M Y');

            $result[] = [
                'month'    => $label,
                'revenue'  => isset($grouped[$key]) ? round($grouped[$key]['revenue'], 2) : 0,
                'bookings' => $grouped[$key]['bookings'] ?? 0,
            ];
        }

        return $result;
    }
    public function getMonthlyRevenueGlobal(int $months = 6): array
    {
        $start = new \DateTimeImmutable("-{$months} months midnight first day of this month");
 
        $rows = $this->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.createdAt >= :start')
            ->setParameter('status', 'CONFIRMED')
            ->setParameter('start', $start)
            ->getQuery()
            ->getResult();
 
        $grouped = [];
        foreach ($rows as $r) {
            $key = $r->getCreatedAt()->format('Y-n');
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['revenue' => 0.0, 'bookings' => 0];
            }
            $grouped[$key]['revenue']  += (float) $r->getTotalAmount();
            $grouped[$key]['bookings'] += 1;
        }
 
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = new \DateTimeImmutable("-{$i} months");
            $key   = $date->format('Y-n');
            $result[] = [
                'month'    => $date->format('M Y'),
                'revenue'  => isset($grouped[$key]) ? round($grouped[$key]['revenue'], 2) : 0,
                'bookings' => $grouped[$key]['bookings'] ?? 0,
            ];
        }
 
        return $result;
    }
}