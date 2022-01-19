<?php

declare(strict_types=1);

namespace IOL\Shop\v1\Enums;

class OrderStatus extends Enum
{
    public const CREATED = 'CREATED';
    public const PAYED = 'PAYED';
    public const FINISHED = 'FINISHED';
}
