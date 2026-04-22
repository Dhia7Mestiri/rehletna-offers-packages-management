<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiOfferService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL   = 'llama-3.3-70b-versatile';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string              $apiKey,
    ) {}

    public function generateDescription(string $title, string $location, array $serviceNames): string
    {
        $servicesList = !empty($serviceNames)
            ? implode(', ', $serviceNames)
            : 'no specific services yet';

        $prompt = "You are a professional travel copywriter for a Tunisian travel platform called Rehletna.tn.\n\n"
            . "Write a compelling, engaging travel offer description for the following:\n\n"
            . "Title: {$title}\n"
            . "Location: {$location}\n"
            . "Included services: {$servicesList}\n\n"
            . "Requirements:\n"
            . "- 3-4 sentences maximum\n"
            . "- Warm, inviting tone that excites potential travelers\n"
            . "- Highlight the destination and key services naturally\n"
            . "- No emojis, no bullet points, just flowing prose\n"
            . "- Write in English\n"
            . "- Do not start with Discover or Experience\n\n"
            . "Return only the description text, nothing else.";

        return $this->call($prompt);
    }

    public function suggestServices(string $title, string $location, array $availableServices): array
    {
        if (empty($availableServices)) {
            return [];
        }

        $servicesList = implode("\n", array_map(
            fn($s) => "- ID:{$s['id']} | {$s['type']} | {$s['name']}" . ($s['location'] ? " | {$s['location']}" : ''),
            $availableServices
        ));

        $prompt = "You are a travel package expert.\n\n"
            . "For this travel offer:\n"
            . "Title: {$title}\n"
            . "Location: {$location}\n\n"
            . "From the available services below, suggest the BEST combination (pick 1-3 services maximum):\n"
            . "{$servicesList}\n\n"
            . "Return ONLY a JSON array of the selected service IDs, like: [1, 5, 12]\n"
            . "No explanation, just the JSON array.";

        $response = $this->call($prompt);

        preg_match('/\[[\d,\s]+\]/', $response, $matches);
        if (empty($matches)) {
            return [];
        }

        return json_decode($matches[0], true) ?? [];
    }

    public function generateTitle(string $location, array $serviceTypes): string
    {
        $types = !empty($serviceTypes)
            ? implode(' and ', array_unique($serviceTypes))
            : 'travel';

        $prompt = "Generate a short, catchy travel offer title for:\n"
            . "Location: {$location}\n"
            . "Services: {$types}\n\n"
            . "Requirements:\n"
            . "- Maximum 8 words\n"
            . "- Exciting and marketable\n"
            . "- No quotes, no punctuation at the end\n"
            . "- Return only the title, nothing else";

        return $this->call($prompt);
    }

    private function call(string $prompt): string
    {
        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . trim($this->apiKey),
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'      => self::MODEL,
                'max_tokens' => 1024,
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
            ],
        ]);

        $data = $response->toArray();

        return trim($data['choices'][0]['message']['content'] ?? '');
    }
}