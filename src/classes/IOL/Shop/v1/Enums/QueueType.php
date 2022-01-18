<?php

declare(strict_types=1);

namespace IOL\Shop\v1\Enums;

class QueueType extends Enum
{
    public const ALL_ORDER = 'iol.shop.order.*';
    public const NEW_ORDER = 'iol.shop.order.new';

    public const MAILER = 'iol.mailer';
}
