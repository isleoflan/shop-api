<?php

declare(strict_types=1);

namespace IOL\Shop\v1\PaymentProvider;

use IOL\Shop\v1\Entity\Order;

interface PaymentProviderInterface
{
    public function createPayment(Order $order): string;
    public function getPaymentLink(): string;
    public function initializeDocuments(Order $order): void;
}