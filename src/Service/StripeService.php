<?php

namespace App\Service;

use App\Entity\Reservation;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripeService
{
    private string $secretKey;
    private string $publicKey;
    private string $appUrl;

    public function __construct(string $secretKey, string $publicKey, string $appUrl)
    {
        $this->secretKey = $secretKey;
        $this->publicKey = $publicKey;
        $this->appUrl    = rtrim($appUrl, '/');
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * Hosted checkout — most compatible, Stripe handles the UI
     */
    public function createCheckoutSession(Reservation $reservation): Session
    {
        Stripe::setApiKey($this->secretKey);

        $offer      = $reservation->getOffer();
        $totalCents = (int) round((float) $reservation->getTotalAmount() * 100);

        return Session::create([
            'payment_method_types' => ['card'],
            'mode'                 => 'payment',
            'line_items'           => [
                [
                    'price_data' => [
                        'currency'     => 'usd',
                        'unit_amount'  => $totalCents,
                        'product_data' => [
                            'name'        => $offer->getTitle(),
                            'description' => sprintf(
                                '%d person(s) · %s → %s',
                                $reservation->getNumberOfPersons(),
                                $offer->getStartDate()?->format('d/m/Y') ?? '—',
                                $offer->getEndDate()?->format('d/m/Y') ?? '—'
                            ),
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'metadata' => [
                'reservation_id' => $reservation->getId(),
            ],
            'success_url' => $this->appUrl . '/reservations/pay/' . $reservation->getId() . '/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $this->appUrl . '/reservations/pay/' . $reservation->getId() . '/cancel',
        ]);
    }

    public function retrieveSession(string $sessionId): Session
    {
        Stripe::setApiKey($this->secretKey);
        return Session::retrieve($sessionId);
    }
}