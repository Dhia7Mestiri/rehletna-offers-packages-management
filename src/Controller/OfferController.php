<?php

namespace App\Controller;

use App\Entity\Offer;
use App\Entity\OfferService;
use App\Entity\User;
use App\Form\OfferType;
use App\Repository\OfferRepository;
use App\Repository\ServiceRepository;
use App\Repository\RatingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;
use App\Repository\ActualiteRepository;
use App\Repository\OfferActivityRepository;


final class OfferController extends AbstractController
{
    #[Route('/offers', name: 'app_offer_index')]
public function index(Request $request, OfferRepository $offerRepository, PaginatorInterface $paginator, ActualiteRepository $actualiteRepo, RatingRepository $ratingRepo): Response
{
    $filters = [
        'q'        => trim((string) $request->query->get('q', '')),
        'location' => trim((string) $request->query->get('location', '')),
        'type'     => trim((string) $request->query->get('type', '')),
        'minPrice' => trim((string) $request->query->get('minPrice', '')),
        'maxPrice' => trim((string) $request->query->get('maxPrice', '')),
        
    ];

    $offers = $paginator->paginate(
        $offerRepository->findActiveWithFiltersQuery($filters),
        $request->query->getInt('page', 1),
        8
    );

    $topDestinations = $offerRepository->findTopDestinations(6);
        $banners = $actualiteRepo->findActiveBanners();
        $offerIds = [];
foreach ($offers as $offer) {
    $offerIds[] = $offer->getId();
}

$ratingsMap = $ratingRepo->getAverageStarsForOffers($offerIds);

    return $this->render('offer/index.html.twig', [
        'offers'          => $offers,
        'filters'         => $filters,
        'topDestinations' => $topDestinations,
        'banners'         => $banners,
            'ratingsMap'      => $ratingsMap,


    ]);
}

#[Route('/offers/{id}', name: 'app_offer_show', requirements: ['id' => '\d+'])]
public function show(
    Offer $offer,
    ServiceRepository $serviceRepository,
    RatingRepository $ratingRepo,
    OfferActivityRepository $activityRepo
): Response {
    $serviceDetails = $serviceRepository->findDetailsByOfferId($offer->getId());
 
    // Capacity
    $bookedSpots = 0;
    foreach ($offer->getReservations() as $r) {
        if ($r->getStatus() === 'CONFIRMED') {
            $bookedSpots += $r->getNumberOfPersons();
        }
    }
    $capacity          = $offer->getCapacity() ?? 0;
    $remainingCapacity = max(0, $capacity - $bookedSpots);
    $percentageFull    = $capacity > 0 ? round(($bookedSpots / $capacity) * 100) : 0;
 
    // Ratings
    $averageStars = $ratingRepo->getAverageStars($offer);
    $totalRatings = $ratingRepo->countByOffer($offer);
    $ratings      = $ratingRepo->findByOffer($offer);
 
    // Activity recommendations
    $activities = $activityRepo->findByLocation($offer->getLocation() ?? '');
 
    return $this->render('offer/show.html.twig', [
        'offer'             => $offer,
        'serviceDetails'    => $serviceDetails,
        'remainingCapacity' => $remainingCapacity,
        'bookedSpots'       => $bookedSpots,
        'capacity'          => $capacity,
        'percentageFull'    => $percentageFull,
        'averageStars'      => $averageStars,
        'totalRatings'      => $totalRatings,
        'ratings'           => $ratings,
        'activities'        => $activities,
    ]);
}
 

    #[Route('/agency/offers', name: 'app_agency_offer_index')]
    public function myOffers(OfferRepository $offerRepository): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User || !in_array('ROLE_AGENCY', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Only agencies can access this page.');
        }

        $offers = $offerRepository->findBy(
            [
                'user' => $user,
                'status' => 'ACTIVE',
            ],
            ['createdAt' => 'DESC']
        );

        return $this->render('offer/agency_index.html.twig', [
            'offers' => $offers,
        ]);
    }

    #[Route('/agency/offers/archived', name: 'app_agency_offer_archived')]
    public function archivedOffers(OfferRepository $offerRepository): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User || !in_array('ROLE_AGENCY', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Only agencies can access this page.');
        }

        $offers = $offerRepository->findBy(
            [
                'user' => $user,
                'status' => 'ARCHIVED',
            ],
            ['updatedAt' => 'DESC']
        );

        return $this->render('offer/archived.html.twig', [
            'offers' => $offers,
        ]);
    }

    #[Route('/agency/offers/new', name: 'app_offer_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ServiceRepository $serviceRepository
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User || !in_array('ROLE_AGENCY', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Only agencies can create offers.');
        }

        $offer = new Offer();
        $offer->setStatus('ACTIVE');
        $offer->setCreatedAt(new \DateTimeImmutable());
        $offer->setUpdatedAt(new \DateTimeImmutable());
        $offer->setUser($user);

        $form = $this->createForm(OfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $selectedServices = $form->get('services')->getData();
            $selectedServicesArray = $selectedServices instanceof \Traversable
                ? iterator_to_array($selectedServices)
                : (array) $selectedServices;

            $errors = $this->validateOffer($offer, $selectedServicesArray);

            if (empty($errors)) {
                $entityManager->persist($offer);

                foreach ($selectedServicesArray as $service) {
                    $offerService = new OfferService();
                    $offerService->setOffer($offer);
                    $offerService->setService($service);
                    $offerService->setCreatedAt(new \DateTimeImmutable());

                    $entityManager->persist($offerService);
                }

                $entityManager->flush();

                $this->addFlash('success', 'Offer created successfully.');

                return $this->redirectToRoute('app_agency_offer_index');
            }

            foreach ($errors as $error) {
                $this->addFlash('danger', $error);
            }
        }

        return $this->render('offer/new.html.twig', [
            'form' => $form->createView(),
            'availableServiceDetails' => $serviceRepository->findAllDetails(),
        ]);
    }

    #[Route('/agency/offers/{id}/edit', name: 'app_offer_edit', requirements: ['id' => '\d+'])]
    public function edit(
        Offer $offer,
        Request $request,
        EntityManagerInterface $entityManager,
        ServiceRepository $serviceRepository
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User || !in_array('ROLE_AGENCY', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Only agencies can edit offers.');
        }

        if (!$offer->getUser() || $offer->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only edit your own offers.');
        }

        $existingServices = [];
        foreach ($offer->getOfferServices() as $offerService) {
            $existingServices[] = $offerService->getService();
        }

        $form = $this->createForm(OfferType::class, $offer);
        $form->get('services')->setData($existingServices);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $selectedServices = $form->get('services')->getData();
            $selectedServicesArray = $selectedServices instanceof \Traversable
                ? iterator_to_array($selectedServices)
                : (array) $selectedServices;

            $errors = $this->validateOffer($offer, $selectedServicesArray);

            if (empty($errors)) {
                $offer->setUpdatedAt(new \DateTimeImmutable());

                $oldOfferServices = $offer->getOfferServices()->toArray();
                foreach ($oldOfferServices as $offerService) {
                    $entityManager->remove($offerService);
                }

                $entityManager->flush();

                foreach ($selectedServicesArray as $service) {
                    $newOfferService = new OfferService();
                    $newOfferService->setOffer($offer);
                    $newOfferService->setService($service);
                    $newOfferService->setCreatedAt(new \DateTimeImmutable());

                    $entityManager->persist($newOfferService);
                }

                $entityManager->flush();

                $this->addFlash('success', 'Offer updated successfully.');

                return $this->redirectToRoute('app_agency_offer_index');
            }

            foreach ($errors as $error) {
                $this->addFlash('danger', $error);
            }
        }

        return $this->render('offer/edit.html.twig', [
            'form' => $form->createView(),
            'offer' => $offer,
            'availableServiceDetails' => $serviceRepository->findAllDetails(),
        ]);
    }

    #[Route('/agency/offers/{id}/archive', name: 'app_offer_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(Offer $offer, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User || !in_array('ROLE_AGENCY', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Only agencies can archive offers.');
        }

        if (!$offer->getUser() || $offer->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only archive your own offers.');
        }

        if (!$this->isCsrfTokenValid('archive_offer_' . $offer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $offer->setStatus('ARCHIVED');
        $offer->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->flush();

        $this->addFlash('success', 'Offer archived successfully.');

        return $this->redirectToRoute('app_agency_offer_index');
    }

    #[Route('/agency/offers/{id}/restore', name: 'app_offer_restore', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function restore(Offer $offer, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User || !in_array('ROLE_AGENCY', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Only agencies can restore offers.');
        }

        if (!$offer->getUser() || $offer->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only restore your own offers.');
        }

        if (!$this->isCsrfTokenValid('restore_offer_' . $offer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $offer->setStatus('ACTIVE');
        $offer->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->flush();

        $this->addFlash('success', 'Offer restored successfully.');

        return $this->redirectToRoute('app_agency_offer_archived');
    }

    #[Route('/agency/offers/{id}/delete', name: 'app_offer_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Offer $offer, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User || !in_array('ROLE_AGENCY', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Only agencies can delete offers.');
        }

        if (!$offer->getUser() || $offer->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only delete your own offers.');
        }

        if (!$this->isCsrfTokenValid('delete_offer_' . $offer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($offer->getStatus() !== 'ARCHIVED') {
            $this->addFlash('danger', 'Only archived offers can be deleted.');
            return $this->redirectToRoute('app_agency_offer_index');
        }

        $entityManager->remove($offer);
        $entityManager->flush();

        $this->addFlash('success', 'Offer deleted successfully.');

        return $this->redirectToRoute('app_agency_offer_archived');
    }

    private function validateOffer(Offer $offer, array $selectedServices): array
    {
        $errors = [];

        $title = trim((string) $offer->getTitle());
        if ($title === '') {
            $errors[] = 'Title is required.';
        } elseif (mb_strlen($title) < 3) {
            $errors[] = 'Title must be at least 3 characters.';
        } elseif (mb_strlen($title) > 150) {
            $errors[] = 'Title cannot exceed 150 characters.';
        }

        $description = trim((string) $offer->getDescription());
        if ($description === '') {
            $errors[] = 'Description is required.';
        } elseif (mb_strlen($description) < 10) {
            $errors[] = 'Description must be at least 10 characters.';
        } elseif (mb_strlen($description) > 2000) {
            $errors[] = 'Description cannot exceed 2000 characters.';
        }

        $location = trim((string) ($offer->getLocation() ?? ''));
        if ($location !== '' && mb_strlen($location) > 150) {
            $errors[] = 'Location cannot exceed 150 characters.';
        }

        $promoPriceRaw = $offer->getPromoPrice();
        $promoPrice = is_numeric((string) $promoPriceRaw) ? (float) $promoPriceRaw : null;

        if ($promoPriceRaw === null || $promoPriceRaw === '' || $promoPrice === null) {
            $errors[] = 'Promotional price is required.';
        } elseif ($promoPrice <= 0) {
            $errors[] = 'Promotional price must be greater than 0.';
        }

        $originalPriceRaw = $offer->getOriginalPrice();
        if ($originalPriceRaw !== null && $originalPriceRaw !== '') {
            $originalPrice = is_numeric((string) $originalPriceRaw) ? (float) $originalPriceRaw : null;

            if ($originalPrice === null) {
                $errors[] = 'Original price is invalid.';
            } elseif ($originalPrice <= 0) {
                $errors[] = 'Original price must be greater than 0.';
            } elseif ($promoPrice !== null && $originalPrice <= $promoPrice) {
                $errors[] = 'Original price must be higher than the promotional price.';
            }
        }

        $today = new \DateTimeImmutable('today');

        $startDate = $offer->getStartDate();
        if (!$startDate) {
            $errors[] = 'Start date is required.';
        } elseif ($startDate < $today) {
            $errors[] = 'Start date cannot be in the past.';
        }

        $endDate = $offer->getEndDate();
        if (!$endDate) {
            $errors[] = 'End date is required.';
        } elseif ($startDate && $endDate <= $startDate) {
            $errors[] = 'End date must be after the start date.';
        }

        $capacity = $offer->getCapacity();
        if ($capacity !== null) {
            if ($capacity < 1) {
                $errors[] = 'Capacity must be at least 1.';
            } elseif ($capacity > 10000) {
                $errors[] = 'Capacity cannot exceed 10,000.';
            }
        }

        $imageUrl = trim((string) ($offer->getImageUrl() ?? ''));
        if ($imageUrl !== '') {
            if (mb_strlen($imageUrl) > 255) {
                $errors[] = 'Image URL cannot exceed 255 characters.';
            } elseif (filter_var($imageUrl, FILTER_VALIDATE_URL) === false) {
                $errors[] = 'Please enter a valid URL.';
            }
        }

        if (count($selectedServices) === 0) {
            $errors[] = 'Please select at least one service.';
        }

        return $errors;
    }
}