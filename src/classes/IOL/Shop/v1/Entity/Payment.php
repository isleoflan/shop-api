<?php

namespace IOL\Shop\v1\Entity;

use IOL\Shop\v1\Content\Discord;
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

    public function __construct(?string $id = null)
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
    public function loadData(array|false $values): void
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
            'time'          => $this->time->format(Date::DATETIME_FORMAT_MICRO),
            'value'         => $this->value
        ]);
    }
    public function sendPaymentDiscordWebhook(): void
    {
        $data = [
            'embeds' => [
                [
                    'title'			=> 'Bezahlung erhalten',
                    'description'	=> 'Eine Bestellung wurde bezahlt',
                    'color'			=> '4859020',
                    'fields'		=> [
                        [
                            'name'		=> 'Bestell-Nr',
                            'value'		=> $this->invoice->getOrder()->getId(),
                            'inline'	=> true,
                        ],
                        [
                            'name'		=> 'Bezahlt von',
                            'value'		=> $this->invoice->getOrder()->username,
                            'inline'	=> true,
                        ],
                        [
                            'name'		=> 'Bezahlt mit',
                            'value'		=> $this->invoice->getOrder()->getPaymentMethod()->getPrettyValue(),
                            'inline'	=> true,
                        ],
                        [
                            'name'		=> 'Betrag',
                            'value'		=> 'CHF '.number_format($this->value / 100,2,'.',"'"),
                            'inline'	=> true,
                        ],
                    ],
                ]
            ]
        ];

        Discord::sendWebhook($data);
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