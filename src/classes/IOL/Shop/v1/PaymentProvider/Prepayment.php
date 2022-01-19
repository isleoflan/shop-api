<?php

declare(strict_types=1);

namespace IOL\Shop\v1\PaymentProvider;

use IOL\Shop\v1\DataSource\Environment;
use IOL\Shop\v1\Entity\Order;

class Prepayment extends PaymentProvider implements PaymentProviderInterface
{
    public int $fixedFee = 0;
    public float $variableFee = 0;

    public function getPaymentLink(): string
    {
        return Environment::get('SUCCESS_URL');
    }

    public function createPayment(Order $order): string
    {
        return '';
    }

    public function initializeDocuments(Order $order): void
    {
        // TODO: Implement initializeDocuments() method.
    }
}