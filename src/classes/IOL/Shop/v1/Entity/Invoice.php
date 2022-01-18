<?php

namespace IOL\Shop\v1\Entity;

use IOL\Shop\v1\DataSource\Database;
use IOL\Shop\v1\DataType\Date;
use IOL\Shop\v1\DataType\UUID;
use IOL\Shop\v1\Exceptions\InvalidValueException;
use IOL\Shop\v1\Exceptions\NotFoundException;

class Invoice
{
    public const DB_TABLE = 'invoices';

    private string $id;
    private Order $order;
    private Date $created;
    private string $externalId;
    private int $value;

    public function __construct(?int $id = null)
    {
        if (!is_null($id)) {
            if (!UUID::isValid($id)) {
                throw new InvalidValueException('Invalid Invoice ID');
            }
            $this->loadData(Database::getRow('id', $id, self::DB_TABLE));
        }
    }

    private function loadData(array|false $values): void
    {

        if (!$values || count($values) === 0) {
            throw new NotFoundException('Invoice could not be loaded');
        }

        $this->id = $values['id'];
        $this->order = new Order($values['order_id']);
        $this->created = new Date($values['created']);
        $this->externalId = $values['external_id'];
        $this->value = $values['value'];
    }

    public function createNew(Order $order, string $externalId): void
    {
        $this->id = UUID::newId(self::DB_TABLE);
        $this->order = $order;
        $this->created = new Date('u');
        $this->externalId = $externalId;
        $this->value = $this->order->getTotal() + $this->order->getFees();

        $database = Database::getInstance();
        $database->insert(self::DB_TABLE, [
            'id'        => $this->id,
            'order_id'  => $this->order->getId(),
            'created'   => $this->created->format(Date::DATETIME_FORMAT_MICRO),
            'externalId'=> $this->externalId,
            'value'     => $this->value
        ]);
    }
}