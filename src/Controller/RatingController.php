<?php

namespace App\Controller;

use App\Entity\Rating;
use App\Repository\RatingRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RatingController extends AbstractController
{
    #[Route('/reservation/{id}/rate', name: 'app_rating_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function submit(
        int $id,
        Request $request,
        ReservationRepository $reservationRepo,
        RatingRepository $ratingRepo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $reservation = $reservationRepo->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found.');
        }

        // Security: only the reservation owner can rate
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Only CONFIRMED reservations can be rated
        if ($reservation->getStatus() !== 'CONFIRMED') {
            $this->addFlash('danger', 'Only confirmed reservations can be rated.');
            return $this->redirectToRoute('app_reservation_my_show', ['id' => $id]);
        }

        // Only after trip end date
        $endDate = $reservation->getOffer()?->getEndDate();
        if ($endDate && $endDate > new \DateTimeImmutable('today')) {
            $this->addFlash('danger', 'Rating is available only after your trip ends.');
            return $this->redirectToRoute('app_reservation_my_show', ['id' => $id]);
        }

        // One rating per reservation
        if ($ratingRepo->findByReservation($reservation)) {
            $this->addFlash('warning', 'You have already rated this trip.');
            return $this->redirectToRoute('app_reservation_my_show', ['id' => $id]);
        }

        $stars = (int) $request->request->get('stars', 5);
        $stars = max(1, min(5, $stars));

        $rating = new Rating();
        $rating->setReservation($reservation);
        $rating->setOffer($reservation->getOffer());
        $rating->setUser($this->getUser());
        $rating->setStars($stars);

        $em->persist($rating);
        $em->flush();

        $this->addFlash('success', '⭐ Thank you for your rating!');
        return $this->redirectToRoute('app_reservation_my_show', ['id' => $id]);
    }
}