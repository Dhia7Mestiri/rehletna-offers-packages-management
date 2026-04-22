<?php

namespace App\Controller;

use App\Repository\ServiceRepository;
use App\Service\AiOfferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ai')]
class AiOfferController extends AbstractController
{
    public function __construct(
        private readonly AiOfferService   $aiService,
        private readonly ServiceRepository $serviceRepo,
    ) {}

    /**
     * Generate a description for an offer.
     * POST /api/ai/description
     * Body: { title, location, serviceIds[] }
     */
    #[Route('/description', name: 'api_ai_description', methods: ['POST'])]
    public function generateDescription(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_AGENCY')) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $data     = json_decode($request->getContent(), true);
        $title    = trim($data['title'] ?? '');
        $location = trim($data['location'] ?? '');
        $ids      = $data['serviceIds'] ?? [];

        if (!$title) {
            return $this->json(['error' => 'Title is required'], Response::HTTP_BAD_REQUEST);
        }

        // Get service names from IDs
        $serviceNames = [];
        if (!empty($ids)) {
            foreach ($ids as $id) {
                $service = $this->serviceRepo->find($id);
                if ($service) {
                    $serviceNames[] = $service->getName() . ' (' . $service->getType() . ')';
                }
            }
        }

        try {
            $description = $this->aiService->generateDescription($title, $location, $serviceNames);
            return $this->json(['description' => $description]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'AI service unavailable: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Suggest best service combination for an offer.
     * POST /api/ai/suggest-services
     * Body: { title, location }
     */
    #[Route('/suggest-services', name: 'api_ai_suggest_services', methods: ['POST'])]
    public function suggestServices(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_AGENCY')) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $data     = json_decode($request->getContent(), true);
        $title    = trim($data['title'] ?? '');
        $location = trim($data['location'] ?? '');

        if (!$title) {
            return $this->json(['error' => 'Title is required'], Response::HTTP_BAD_REQUEST);
        }

        // Get all available services
        $allServices = $this->serviceRepo->findAll();
        $serviceData = array_map(fn($s) => [
            'id'       => $s->getId(),
            'type'     => $s->getType(),
            'name'     => $s->getName(),
            'location' => $s->getHotel()?->getLocation() ?? $s->getVol()?->getDepartureCity() ?? null,
        ], $allServices);

        try {
            $suggestedIds = $this->aiService->suggestServices($title, $location, $serviceData);
            return $this->json(['suggestedIds' => $suggestedIds]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'AI service unavailable: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate a title suggestion.
     * POST /api/ai/title
     * Body: { location, serviceTypes[] }
     */
    #[Route('/title', name: 'api_ai_title', methods: ['POST'])]
    public function generateTitle(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_AGENCY')) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $data         = json_decode($request->getContent(), true);
        $location     = trim($data['location'] ?? '');
        $serviceTypes = $data['serviceTypes'] ?? [];

        if (!$location) {
            return $this->json(['error' => 'Location is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $title = $this->aiService->generateTitle($location, $serviceTypes);
            return $this->json(['title' => $title]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'AI service unavailable: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}