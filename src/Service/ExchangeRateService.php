<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ExchangeRateService
{
    private string $apiKey;
    private string $baseCurrency;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface      $cache,
        string $apiKey,
        string $baseCurrency = 'TND',
    ) {
        $this->apiKey       = $apiKey;
        $this->baseCurrency = strtoupper($baseCurrency);
    }

    /**
     * Get all exchange rates from base currency.
     * Cached for 1 hour to avoid burning API quota.
     */
    public function getRates(): array
    {
        return $this->cache->get('exchange_rates_' . $this->baseCurrency, function (ItemInterface $item) {
            $item->expiresAfter(3600); // cache 1 hour

            $response = $this->httpClient->request('GET',
                "https://v6.exchangerate-api.com/v6/{$this->apiKey}/latest/{$this->baseCurrency}"
            );

            $data = $response->toArray();

            if ($data['result'] !== 'success') {
                throw new \RuntimeException('Exchange rate API error: ' . ($data['error-type'] ?? 'unknown'));
            }

            return $data['conversion_rates'];
        });
    }

    /**
     * Convert an amount from base currency to target currency.
     */
    public function convert(float $amount, string $targetCurrency): float
    {
        $rates = $this->getRates();
        $target = strtoupper($targetCurrency);

        if (!isset($rates[$target])) {
            throw new \InvalidArgumentException("Currency {$target} not supported.");
        }

        return round($amount * $rates[$target], 2);
    }

    /**
     * Get rates for specific currencies only.
     */
    public function getSelectedRates(array $currencies = ['USD', 'EUR', 'GBP', 'SAR', 'MAD']): array
    {
        $all    = $this->getRates();
        $result = [];

        foreach ($currencies as $currency) {
            $currency = strtoupper($currency);
            if (isset($all[$currency])) {
                $result[$currency] = $all[$currency];
            }
        }

        return $result;
    }

    /**
     * Convert an amount to multiple currencies at once.
     */
    public function convertToMany(float $amount, array $currencies = ['USD', 'EUR', 'GBP']): array
    {
        $rates  = $this->getSelectedRates($currencies);
        $result = [];

        foreach ($rates as $currency => $rate) {
            $result[$currency] = [
                'rate'   => $rate,
                'amount' => round($amount * $rate, 2),
                'symbol' => $this->getSymbol($currency),
            ];
        }

        return $result;
    }

    private function getSymbol(string $currency): string
    {
        return match($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'SAR' => '﷼',
            'MAD' => 'د.م.',
            'DZD' => 'دج',
            'JPY' => '¥',
            'CAD' => 'CA$',
            default => $currency,
        };
    }
}