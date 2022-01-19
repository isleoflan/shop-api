<?php

declare(strict_types=1);

namespace IOL\Shop\v1\PaymentProvider;

class PaymentProvider
{
    public function getFees(int $total): int
    {
        return (int)(ceil((($total * $this->variableFee) + $this->fixedFee) / 50) * 50);
    }

}