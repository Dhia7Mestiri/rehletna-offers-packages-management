<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LocationController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    #[Route('/api/location/autocomplete', name: 'api_location_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        try {
            $response = $this->httpClient->request('GET',
                'https://nominatim.openstreetmap.org/search', [
                'query' => [
                    'q'              => $query,
                    'format'         => 'json',
                    'limit'          => 6,
                    'addressdetails' => 1,
                ],
                'headers' => [
                    'Accept-Language' => 'en',
                    'User-Agent'      => 'Rehletna.tn Travel Platform',
                ],
                'timeout' => 5,
            ]);

            $data    = $response->toArray();
            $results = [];

            foreach ($data as $place) {
                $addr    = $place['address'] ?? [];
                $city    = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? $addr['county'] ?? '';
                $country = $addr['country'] ?? '';
                $label   = $city && $country ? $city . ', ' . $country : ($place['display_name'] ?? '');

                if ($label && !in_array($label, array_column($results, 'label'))) {
                    $results[] = ['label' => $label];
                }
            }

            return $this->json($results);

        } catch (\Throwable) {
            return $this->json([]);
        }
    }
}