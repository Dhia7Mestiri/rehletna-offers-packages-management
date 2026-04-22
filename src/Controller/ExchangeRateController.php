<?php

namespace App\Controller;

use App\Service\ExchangeRateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/exchange')]
class ExchangeRateController extends AbstractController
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRate,
    ) {}

    /**
     * Convert an amount to multiple currencies.
     * GET /api/exchange/convert?amount=1000&currencies=USD,EUR,GBP
     */
    #[Route('/convert', name: 'api_exchange_convert', methods: ['GET'])]
    public function convert(Request $request): JsonResponse
    {
        $amount     = (float) $request->query->get('amount', 0);
        $currencies = explode(',', $request->query->get('currencies', 'EUR,USD,GBP,SAR'));

        if ($amount <= 0) {
            return $this->json(['error' => 'Invalid amount'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $conversions = $this->exchangeRate->convertToMany($amount, $currencies);
            return $this->json([
                'base'        => 'TND',
                'amount'      => $amount,
                'conversions' => $conversions,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get current rates.
     * GET /api/exchange/rates
     */
    #[Route('/rates', name: 'api_exchange_rates', methods: ['GET'])]
    public function rates(): JsonResponse
    {
        try {
            $rates = $this->exchangeRate->getSelectedRates(['USD', 'EUR', 'GBP', 'SAR', 'MAD', 'DZD', 'CAD', 'JPY']);
            return $this->json(['base' => 'TND', 'rates' => $rates]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}