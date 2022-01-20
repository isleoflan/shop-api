<?php

declare(strict_types=1);

namespace IOL\Shop\v1\Enums;

use JetBrains\PhpStorm\Pure;

class PaymentMethod extends Enum
{
    public const PREPAYMENT = 'PREPAYMENT';
    public const STRIPE = 'STRIPE';
    public const PAYPAL = 'PAYPAL';
    public const CRYPTO = 'CRYPTO';

    #[Pure]
    public function getPrettyValue(): string
    {
        return match($this->getValue()) {
            PaymentMethod::PREPAYMENT => 'Vorauskasse',
            PaymentMethod::CRYPTO => 'Kryptowährungen',
            PaymentMethod::PAYPAL => 'PayPal',
            PaymentMethod::STRIPE => 'Kreditkarte'
        };
    }
}
