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

    public function __construct(?string $id = null)
    {
        if (!is_null($id)) {
            if (!UUID::isValid($id)) {
                throw new InvalidValueException('Invalid Invoice ID');
            }
            $this->loadData(Database::getRow('id', $id, self::DB_TABLE));
        }
    }

    /**
     * @throws NotFoundException|InvalidValueException
     */
    public function getForOrder(Order $order)
    {
        $database = Database::getInstance();
        $database->where('order_id', $order->getId());
        $row = $database->get(self::DB_TABLE, 1);
        $this->loadData($row[0]);
    }

    /**
     * @throws NotFoundException
     * @throws InvalidValueException
     */
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
        $this->value = $this->order->getTotal();

        $database = Database::getInstance();
        $database->insert(self::DB_TABLE, [
            'id'            => $this->id,
            'order_id'      => $this->order->getId(),
            'created'       => $this->created->format(Date::DATETIME_FORMAT_MICRO),
            'external_id'   => $this->externalId,
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

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }


    public function createPayment(int $value)
    {
        $payment = new Payment();
        $payment->createNew($this, $value);
    }

    public function generatePDF(): string
    {
return '';
    }
}