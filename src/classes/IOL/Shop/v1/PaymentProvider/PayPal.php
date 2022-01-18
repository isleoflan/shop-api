<?php

declare(strict_types=1);

namespace IOL\Shop\v1\PaymentProvider;

use IOL\Shop\v1\Entity\Order;

class PayPal extends PaymentProvider implements PaymentProviderInterface
{
    public int $fixedFee = 55;
    public float $variableFee = 0.034;

    public function getPaymentLink(): ?string
    {
        return '';
    }

    public function initializeDocuments(Order $order): void
    {
        // TODO: Implement initializeDocuments() method.
    }

    public function createPayment(Order $order): string
    {
        return '';
    }
}