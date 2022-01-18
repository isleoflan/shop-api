<?php

declare(strict_types=1);

namespace IOL\Shop\v1\Enums;

class OrderDocumentType extends Enum
{
    public const TICKET = 'TICKET';
    public const INVOICE = 'INVOICE';
    public const RECEIPT = 'RECEIPT';
}
