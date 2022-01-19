<?php

declare(strict_types=1);

namespace IOL\Shop\v1\Entity;

use IOL\Shop\v1\DataSource\Database;
use IOL\Shop\v1\DataType\Date;
use IOL\Shop\v1\DataType\UUID;
use IOL\Shop\v1\Exceptions\InvalidValueException;
use IOL\Shop\v1\Exceptions\NotFoundException;

class Voucher
{
    public const DB_TABLE = 'vouchers';

    private string $code;
    private int $value;
    private ?Date $used;

    /**
     * @throws \IOL\Shop\v1\Exceptions\InvalidValueException
     * @throws NotFoundException
     */
    public function __construct(?string $code = null)
    {
        if (!is_null($code)) {
            $this->loadData(Database::getRow('code', $code, self::DB_TABLE));
        }
    }

    /**
     * @throws NotFoundException
     */
    public function loadData(array|false $values)
    {
        if (!$values || count($values) === 0) {
            throw new NotFoundException('Voucher could not be loaded');
        }

        $this->code = $values['code'];
        $this->value = $values['value'];
        $this->used = is_null($values['used']) ? null : new Date($values['used']);
    }

    public function isValid(): bool
    {
        return is_null($this->used);
    }

    /**
     * @return int
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }


    public function consume(): void
    {
        $database = Database::getInstance();
        $database->where('code', $this->code);
        $database->update(self::DB_TABLE, [
            'used' => Date::now(Date::DATETIME_FORMAT_MICRO)
        ]);
    }
}