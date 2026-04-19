<?php

namespace App\Controller;

use App\Repository\OfferRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class AdminOfferController extends AbstractController
{
    #[Route('/offers', name: 'app_admin_offers')]
    public function offers(
        Request $request,
        OfferRepository $offerRepo,
        ReservationRepository $reservationRepo,
        UserRepository $userRepo
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        // Filters
        $filters = [
            'q'        => trim((string) $request->query->get('q', '')),
            'status'   => trim((string) $request->query->get('status', '')),
            'agency'   => trim((string) $request->query->get('agency', '')),
            'type'     => trim((string) $request->query->get('type', '')),
        ];

        $offers = $offerRepo->findAllWithFilters($filters);

        // Global stats
        $allReservations = $reservationRepo->findAll();
        $allOffers       = $offerRepo->findAll();
        $agencies        = $userRepo->findByRole('AGENCY');

        $stats = [
            'total_offers'       => count($allOffers),
            'active_offers'      => count(array_filter($allOffers, fn($o) => $o->getStatus() === 'ACTIVE')),
            'archived_offers'    => count(array_filter($allOffers, fn($o) => $o->getStatus() === 'ARCHIVED')),
            'total_reservations' => count($allReservations),
            'confirmed'          => count(array_filter($allReservations, fn($r) => $r->getStatus() === 'CONFIRMED')),
            'pending'            => count(array_filter($allReservations, fn($r) => $r->getStatus() === 'PENDING')),
            'cancelled'          => count(array_filter($allReservations, fn($r) => $r->getStatus() === 'CANCELLED')),
            'total_revenue'      => array_sum(array_map(
                fn($r) => $r->getStatus() === 'CONFIRMED' ? (float) $r->getTotalAmount() : 0,
                $allReservations
            )),
            'total_agencies'     => count($agencies),
            'total_travellers'   => array_sum(array_map(
                fn($r) => $r->getStatus() === 'CONFIRMED' ? $r->getNumberOfPersons() : 0,
                $allReservations
            )),
        ];

        $monthlyData = $reservationRepo->getMonthlyRevenueGlobal(6);

        return $this->render('admin/offers.html.twig', [
            'offers'      => $offers,
            'filters'     => $filters,
            'stats'       => $stats,
            'monthlyData' => $monthlyData,
            'agencies'    => $agencies,
        ]);
    }

    #[Route('/offers/{id}', name: 'app_admin_offer_show', requirements: ['id' => '\d+'])]
    public function showOffer(int $id, OfferRepository $offerRepo, ReservationRepository $reservationRepo): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $offer = $offerRepo->find($id);
        if (!$offer) {
            throw $this->createNotFoundException('Offer not found.');
        }

        $reservations = $reservationRepo->findBy(['offer' => $offer], ['createdAt' => 'DESC']);

        $offerStats = [
            'total'     => count($reservations),
            'confirmed' => count(array_filter($reservations, fn($r) => $r->getStatus() === 'CONFIRMED')),
            'pending'   => count(array_filter($reservations, fn($r) => $r->getStatus() === 'PENDING')),
            'cancelled' => count(array_filter($reservations, fn($r) => $r->getStatus() === 'CANCELLED')),
            'revenue'   => array_sum(array_map(
                fn($r) => $r->getStatus() === 'CONFIRMED' ? (float) $r->getTotalAmount() : 0,
                $reservations
            )),
        ];

        return $this->render('admin/offer_show.html.twig', [
            'offer'        => $offer,
            'reservations' => $reservations,
            'offerStats'   => $offerStats,
        ]);
    }
}