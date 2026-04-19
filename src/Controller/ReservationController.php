<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Form\ReservationType;
use App\Repository\OfferRepository;
use App\Repository\ReservationRepository;
use App\Service\MailerSendService;
use App\Service\PdfTicketService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reservations')]
final class ReservationController extends AbstractController
{
    public function __construct(
        private readonly StripeService     $stripe,
        private readonly MailerSendService $mailer,
        private readonly PdfTicketService  $pdfTicket,
    ) {}

    #[Route('/book/{id}', name: 'app_reservation_book', requirements: ['id' => '\d+'])]
    public function book(int $id, Request $request, OfferRepository $offerRepository, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isGranted('ROLE_AGENCY') || $this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Agencies and admins cannot make reservations.');
            return $this->redirectToRoute('app_offer_index');
        }

        $offer = $offerRepository->find($id);
        if (!$offer || $offer->getStatus() !== 'ACTIVE') {
            throw $this->createNotFoundException('Offer not found or no longer available.');
        }

        $bookedSpots = 0;
        foreach ($offer->getReservations() as $r) {
            if ($r->getStatus() === 'CONFIRMED') {
                $bookedSpots += $r->getNumberOfPersons();
            }
        }
        $remainingCapacity = ($offer->getCapacity() ?? 999) - $bookedSpots;

        if ($remainingCapacity <= 0) {
            $this->addFlash('danger', 'Sorry, this offer is fully booked.');
            return $this->redirectToRoute('app_offer_show', ['id' => $id]);
        }

        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation, ['max_capacity' => $remainingCapacity]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $total = bcmul($offer->getPromoPrice(), (string) $reservation->getNumberOfPersons(), 2);

            $reservation->setOffer($offer);
            $reservation->setUser($user);
            $reservation->setReservationDate(new \DateTimeImmutable());
            $reservation->setTotalAmount($total);
            $reservation->setStatus('PENDING');
            $reservation->setPaymentStatus('UNPAID');
            $reservation->setCreatedAt(new \DateTimeImmutable());

            $em->persist($reservation);
            $em->flush();

            return $this->redirectToRoute('app_reservation_checkout', ['id' => $reservation->getId()]);
        }

        return $this->render('reservation/book.html.twig', [
            'offer'             => $offer,
            'form'              => $form->createView(),
            'remainingCapacity' => $remainingCapacity,
            'pricePerPerson'    => $offer->getPromoPrice(),
        ]);
    }


    #[Route('/checkout/{id}', name: 'app_reservation_checkout', requirements: ['id' => '\d+'])]
    public function checkout(int $id, ReservationRepository $repo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reservation = $repo->find($id);
        if (!$reservation || $reservation->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Reservation not found.');
        }

        if ($reservation->getPaymentStatus() === 'PAID') {
            return $this->redirectToRoute('app_reservation_my_show', ['id' => $id]);
        }

        $session = $this->stripe->createCheckoutSession($reservation);

        return $this->render('reservation/checkout.html.twig', [
            'reservation'     => $reservation,
            'stripeUrl'       => $session->url,
            'stripePublicKey' => $this->stripe->getPublicKey(),
        ]);
    }

    #[Route('/pay/{id}/success', name: 'app_reservation_pay_success', requirements: ['id' => '\d+'])]
    public function paySuccess(int $id, Request $request, ReservationRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reservation = $repo->find($id);
        if (!$reservation || $reservation->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Reservation not found.');
        }

        $sessionId = $request->query->get('session_id');

        if ($sessionId && $reservation->getPaymentStatus() !== 'PAID') {
            try {
                $session = $this->stripe->retrieveSession($sessionId);

                if ($session->payment_status === 'paid') {
    $reservation->setPaymentStatus('PAID');
    $reservation->setStatus('CONFIRMED');
    $reservation->setUpdatedAt(new \DateTimeImmutable());
    $em->flush();

    // TEMPORARY DEBUG — remove after fixing
    try {
        $pdfPath = $this->pdfTicket->generateToFile($reservation);
    } catch (\Throwable $e) {
        file_put_contents(sys_get_temp_dir() . '/pdf_error.log', $e->getMessage());
        $pdfPath = null;
    }

    try {
        $this->mailer->sendReservationConfirmation($reservation, $pdfPath);
    } catch (\Throwable $e) {
        file_put_contents(sys_get_temp_dir() . '/mail_error.log', $e->getMessage());
    }
}
            } catch (\Throwable) {
                // Stripe verification failed
            }
        }

        return $this->render('reservation/pay_success.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/pay/{id}/cancel', name: 'app_reservation_pay_cancel', requirements: ['id' => '\d+'])]
    public function payCancel(int $id, ReservationRepository $repo): Response
    {
        $reservation = $repo->find($id);

        return $this->render('reservation/pay_cancel.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/my/{id}/cancel', name: 'app_reservation_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id, Request $request, ReservationRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reservation = $repo->find($id);
        if (!$reservation || $reservation->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Reservation not found.');
        }

        if (!$this->isCsrfTokenValid('cancel_reservation_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($reservation->getPaymentStatus() === 'PAID') {
            $this->addFlash('danger', 'Paid reservations cannot be cancelled online. Please contact the agency.');
            return $this->redirectToRoute('app_reservation_my_show', ['id' => $id]);
        }

        $reservation->setStatus('CANCELLED');
        $reservation->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Reservation cancelled.');
        return $this->redirectToRoute('app_reservation_my_list');
    }

    #[Route('/my', name: 'app_reservation_my_list')]
    public function myList(ReservationRepository $repo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('reservation/my_list.html.twig', [
            'reservations' => $repo->findBy(['user' => $user], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/my/{id}', name: 'app_reservation_my_show', requirements: ['id' => '\d+'])]
    public function myShow(int $id, ReservationRepository $repo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $reservation = $repo->find($id);
        if (!$reservation || $reservation->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Reservation not found.');
        }

        return $this->render('reservation/my_show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/agency', name: 'app_reservation_agency_list')]
    public function agencyDashboard(ReservationRepository $repo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isGranted('ROLE_AGENCY')) {
            throw $this->createAccessDeniedException('Agencies only.');
        }

        $reservations = $repo->findByAgency($user);

        $stats = [
            'total'     => count($reservations),
            'confirmed' => count(array_filter($reservations, fn($r) => $r->getStatus() === 'CONFIRMED')),
            'pending'   => count(array_filter($reservations, fn($r) => $r->getStatus() === 'PENDING')),
            'cancelled' => count(array_filter($reservations, fn($r) => $r->getStatus() === 'CANCELLED')),
            'revenue'   => array_sum(array_map(
                fn($r) => $r->getStatus() === 'CONFIRMED' ? (float) $r->getTotalAmount() : 0,
                $reservations
            )),
            'persons'   => array_sum(array_map(
                fn($r) => $r->getStatus() === 'CONFIRMED' ? $r->getNumberOfPersons() : 0,
                $reservations
            )),
        ];

        $monthlyData = $repo->getMonthlyRevenueByAgency($user, 6);

        return $this->render('reservation/agency_dashboard.html.twig', [
            'reservations' => $reservations,
            'stats'        => $stats,
            'monthlyData'  => $monthlyData,
        ]);
    }

    #[Route('/agency/{id}', name: 'app_reservation_agency_show', requirements: ['id' => '\d+'])]
    public function agencyShow(int $id, ReservationRepository $repo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isGranted('ROLE_AGENCY')) {
            throw $this->createAccessDeniedException('Agencies only.');
        }

        $reservation = $repo->find($id);
        if (!$reservation || $reservation->getOffer()->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Reservation not found.');
        }

        return $this->render('reservation/agency_show.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}