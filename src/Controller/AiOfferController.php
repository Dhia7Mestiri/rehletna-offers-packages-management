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
#[Route('/smart-search', name: 'api_ai_smart_search', methods: ['POST'])]
public function smartSearch(Request $request): JsonResponse
{
    $data  = json_decode($request->getContent(), true);
    $query = trim((string) ($data['query'] ?? ''));
 
    if (!$query) {
        return $this->json(['error' => 'Query is required.'], 400);
    }
 
    $prompt = <<<PROMPT
You are a travel search assistant. Extract search filters from this natural language query: "{$query}"
 
Return ONLY a JSON object with these fields (omit fields you can't determine):
- "q": keyword search (title or destination)
- "location": city or country name
- "type": either "HOTEL" or "VOL" (only if clearly mentioned)
- "minPrice": minimum price in TND (number only)
- "maxPrice": maximum price in TND (number only)
 
Examples:
- "beach trip in Djerba under 800 TND" → {"location": "Djerba", "maxPrice": 800}
- "flight to Paris" → {"type": "VOL", "location": "Paris"}
- "family hotel under 500" → {"type": "HOTEL", "maxPrice": 500}
- "5 days in Turkey between 1000 and 2000 TND" → {"location": "Turkey", "minPrice": 1000, "maxPrice": 2000}
 
Return ONLY the JSON object, no explanation, no markdown.
PROMPT;
 
    try {
        $result = $this->aiService->callGroq($prompt);
        $clean  = trim(preg_replace('/^```json|```$/m', '', $result));
        $parsed = json_decode($clean, true);
 
        if (!is_array($parsed)) {
            return $this->json(['error' => 'Could not parse AI response.'], 500);
        }
 
        return $this->json($parsed);
 
    } catch (\Throwable $e) {
        return $this->json(['error' => $e->getMessage()], 500);
    }
}
#[Route('/generate-offer', name: 'api_ai_generate_offer', methods: ['POST'])]
public function generateOffer(Request $request): JsonResponse
{
    $data   = json_decode($request->getContent(), true);
    $prompt = trim((string) ($data['prompt'] ?? ''));
 
    if (!$prompt) {
        return $this->json(['error' => 'Prompt is required.'], 400);
    }
 
    $today     = date('Y-m-d');
    $nextMonth = date('Y-m-d', strtotime('+30 days'));
 
    $systemPrompt = <<<PROMPT
You are a travel offer creation assistant. Extract structured data from this offer description: "{$prompt}"
 
Return ONLY a valid JSON object with these fields:
- "title": catchy offer title (max 100 chars)
- "location": city and country (e.g. "Sousse, Tunisia")
- "description": compelling 3-4 sentence description for travelers
- "promoPrice": promotional price as number (TND)
- "originalPrice": original price as number, 15-25% higher than promoPrice (TND)
- "startDate": start date in Y-m-d format (use dates from prompt or default to {$nextMonth})
- "endDate": end date in Y-m-d format (must be after startDate)
 
Rules:
- If price not mentioned, suggest a reasonable price for the destination
- If dates not mentioned, use upcoming dates starting from {$nextMonth}
- Description should be engaging and mention key highlights
- Return ONLY the JSON object, no markdown, no explanation
PROMPT;
 
    try {
        $result = $this->aiService->callGroq($systemPrompt);
        $clean  = trim(preg_replace('/^```json|^```|```$/m', '', $result));
        $parsed = json_decode($clean, true);
 
        if (!is_array($parsed)) {
            return $this->json(['error' => 'Could not parse AI response. Please try again.'], 500);
        }
 
        return $this->json($parsed);
 
    } catch (\Throwable $e) {
        return $this->json(['error' => $e->getMessage()], 500);
    }
}

}