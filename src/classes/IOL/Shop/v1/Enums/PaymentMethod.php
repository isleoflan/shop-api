<?php

declare(strict_types=1);

namespace IOL\Shop\v1\Enums;

class PaymentMethod extends Enum
{
    public const PREPAYMENT = 'PREPAYMENT';
    public const STRIPE = 'STRIPE';
    public const PAYPAL = 'PAYPAL';
    public const CRYPTO = 'CRYPTO';
}
