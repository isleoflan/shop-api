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

    public function getPaymentLink(): ?string
    {
        return '';
    }

    public function createPayment(Order $order): string
    {
        ApiClient::init(Environment::get('COINBASE_SECRET'));

        $checkoutData = [
            'name' => 'Bestellung bei Isle of LAN',
            'description' => 'Bestell-Nr. '.$order->getId(),
            'pricing_type' => 'fixed_price',
            'local_price' => [
                'amount' => number_format(($order->getTotal() + $order->getFees()), 2, '.', ''),
                'currency' => 'CHF'
            ],
            'requested_info' => ['name', 'email']
        ];
        $newCheckoutObj = Checkout::create($checkoutData);

        var_dump($newCheckoutObj);

        return '';
    }

    public function initializeDocuments(Order $order): void
    {
        // TODO: Implement initializeDocuments() method.
    }
}