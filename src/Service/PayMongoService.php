<?php

namespace App\Service;

use App\Entity\Payment;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PayMongoService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $payMongoSecretKey,
        private string $successUrl,
        private string $cancelUrl
    ) {
    }

    public function createCheckoutUrl(Payment $payment): string
    {
        if (!$this->payMongoSecretKey) {
            throw new \RuntimeException('PayMongo secret key is missing. Set PAYMONGO_SECRET_KEY in environment.');
        }

        $amount = (int) round(((float) $payment->getAmount()) * 100);
        $reference = sprintf('payment-%d', $payment->getId() ?? time());

        $response = $this->httpClient->request('POST', 'https://api.paymongo.com/v1/checkout_sessions', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->payMongoSecretKey . ':'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'data' => [
                    'attributes' => [
                        'line_items' => [[
                            'name' => 'Booking Payment',
                            'amount' => $amount,
                            'currency' => 'PHP',
                            'quantity' => 1,
                        ]],
                        'payment_method_types' => ['card', 'gcash', 'paymaya'],
                        'success_url' => $this->successUrl,
                        'cancel_url' => $this->cancelUrl,
                        'description' => 'Payment for booking #' . ($payment->getBooking()?->getId() ?? 'N/A'),
                        'reference_number' => $reference,
                    ],
                ],
            ],
        ]);

        $payload = $response->toArray(false);
        $checkoutUrl = $payload['data']['attributes']['checkout_url'] ?? null;
        if (!$checkoutUrl) {
            throw new \RuntimeException('Unable to create PayMongo checkout session.');
        }

        return $checkoutUrl;
    }
}
