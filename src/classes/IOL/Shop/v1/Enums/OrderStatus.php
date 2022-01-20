<?php

declare(strict_types=1);

namespace IOL\Shop\v1\Enums;

class OrderStatus extends Enum
{
    public const CREATED = 'CREATED';
    public const CANCELLED = 'CANCELLED';
    public const FINISHED = 'FINISHED';
}
