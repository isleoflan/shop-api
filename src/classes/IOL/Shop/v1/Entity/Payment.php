<?php

namespace IOL\Shop\v1\Entity;

use IOL\Shop\v1\DataSource\Database;
use IOL\Shop\v1\DataType\Date;
use IOL\Shop\v1\DataType\UUID;
use IOL\Shop\v1\Exceptions\InvalidValueException;
use IOL\Shop\v1\Exceptions\NotFoundException;

class Payment
{
    public const DB_TABLE = 'invoice_payments';

    private string $id;
    private Invoice $invoice;
    private int $value;
    private Date $time;

    public function __construct(?int $id = null)
    {
        if (!is_null($id)) {
            if (!UUID::isValid($id)) {
                throw new InvalidValueException('Invalid Payment ID');
            }
            $this->loadData(Database::getRow('id', $id, self::DB_TABLE));
        }
    }

    /**
     * @throws NotFoundException
     * @throws InvalidValueException
     */
    private function loadData(array|false $values): void
    {

        if (!$values || count($values) === 0) {
            throw new NotFoundException('Payment could not be loaded');
        }

        $this->id = $values['id'];
        $this->invoice = new Invoice($values['invoice_id']);
        $this->value = $values['value'];
        $this->time = new Date($values['time']);
    }

    public function createNew(Invoice $invoice, int $value): void
    {
        $this->id = UUID::newId(self::DB_TABLE);
        $this->invoice = $invoice;
        $this->time = new Date('u');
        $this->value = $value;

        $database = Database::getInstance();
        $database->insert(self::DB_TABLE, [
            'id'            => $this->id,
            'invoice_id'    => $this->invoice->getId(),
            'created'       => $this->time->format(Date::DATETIME_FORMAT_MICRO),
            'value'         => $this->value
        ]);
    }

    /**
     * @return int
     */
    public function getValue(): int
    {
        return $this->value;
    }

    public function createPayment(int $value)
    {

    }
}