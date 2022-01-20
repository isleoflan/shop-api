<?php

declare(strict_types=1);

namespace IOL\Shop\v1\PaymentProvider;

use CoinbaseCommerce\ApiClient;
use CoinbaseCommerce\Resources\Checkout;
use IOL\Shop\v1\DataSource\Environment;
use IOL\Shop\v1\Entity\Order;

class Crypto extends PaymentProvider implements PaymentProviderInterface
{
    public int $fixedFee = 0;
    public float $variableFee = 0;

    private string $id;

    public function getPaymentLink(): string
    {
        return 'https://commerce.coinbase.com/checkout/'.$this->id;
    }

    public function createPayment(Order $order): string
    {
        ApiClient::init(Environment::get('COINBASE_SECRET'));

        $checkoutData = [
            'name' => 'Bestellung bei Isle of LAN',
            'description' => 'Bestell-Nr. '.$order->getId(),
            'pricing_type' => 'fixed_price',
            'local_price' => [
                'amount' => number_format(($order->getTotal()) / 100, 2, '.', ''),
                'currency' => 'CHF'
            ],
            'requested_info' => []
        ];
        $newCheckoutObj = Checkout::create($checkoutData);
        $this->id = $newCheckoutObj->getAttribute('id');

        return $this->id;
    }

    public function initializeDocuments(Order $order): void
    {
        // TODO: Implement initializeDocuments() method.
    }
}