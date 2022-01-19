<?php

declare(strict_types=1);

namespace IOL\Shop\v1\Entity;

use IOL\Shop\v1\DataSource\Database;
use IOL\Shop\v1\DataType\Date;
use IOL\Shop\v1\DataType\UUID;
use IOL\Shop\v1\Enums\OrderStatus;
use IOL\Shop\v1\Enums\PaymentMethod;
use IOL\Shop\v1\Exceptions\InvalidValueException;
use IOL\Shop\v1\Exceptions\IOLException;
use IOL\Shop\v1\Exceptions\NotFoundException;
use IOL\Shop\v1\PaymentProvider\Crypto;
use IOL\Shop\v1\PaymentProvider\PayPal;
use IOL\Shop\v1\PaymentProvider\Prepayment;
use IOL\Shop\v1\PaymentProvider\Stripe;
use IOL\Shop\v1\Request\APIResponse;
use JetBrains\PhpStorm\Pure;

class Order
{
    public const DB_TABLE = 'orders';

    private string $id;
    private string $userId;
    private Date $created;
    private PaymentMethod $paymentMethod;
    private ?Voucher $voucher = null;
    private OrderStatus $orderStatus;

    private array $items = [];

    public function __construct(?int $id = null)
    {
        if (!is_null($id)) {
            if (!UUID::isValid($id)) {
                throw new InvalidValueException('Invalid Order ID');
            }
            $this->loadData(Database::getRow('id', $id, self::DB_TABLE));
        }
    }

    private function loadData(array|false $values): void
    {

        if (!$values || count($values) === 0) {
            throw new NotFoundException('Order could not be loaded');
        }

        $this->id = $values['id'];
        $this->userId = $values['user_id'];
        $this->created = new Date($values['created']);
        $this->paymentMethod = new PaymentMethod($values['payment_method']);
    }

    public function createNew(string $userId, array $items, PaymentMethod $paymentMethod, ?Voucher $voucher): string
    {
        $this->id = UUID::newId(self::DB_TABLE);
        $this->userId = $userId;
        $this->created = new Date('u');
        $this->paymentMethod = $paymentMethod;
        $this->voucher = $voucher;
        $this->orderStatus = new OrderStatus(OrderStatus::CREATED);


        foreach($items as $sort => $item){
            $orderItem = new OrderItem();
            $orderItem->createNew($this->id, $item, $sort);

            $this->items[] = $orderItem;
        }

        if($this->getTotal() === 0){
            $this->paymentMethod = new PaymentMethod(PaymentMethod::PREPAYMENT);
        }

        $database = Database::getInstance();
        $database->insert(self::DB_TABLE, [
            'id' => $this->id,
            'user_id' => $this->userId,
            'created' => $this->created->format(Date::DATETIME_FORMAT_MICRO),
            'payment_method' => $this->paymentMethod->getValue(),
            'voucher' => is_null($this->voucher) ? null : $this->voucher->getCode(),
            'status' => $this->orderStatus->getValue()
        ]);

        switch($this->paymentMethod->getValue()){
            case PaymentMethod::PREPAYMENT:
                $paymentProvider = new Prepayment();
                break;
            case PaymentMethod::STRIPE:
                $paymentProvider = new Stripe();
                break;
            case PaymentMethod::PAYPAL:
                $paymentProvider = new PayPal();
                break;
            case PaymentMethod::CRYPTO:
                $paymentProvider = new Crypto();
                break;
        }

        $externalId = $paymentProvider->createPayment($this);
        $redirect = $paymentProvider->getPaymentLink();

        $invoice = new Invoice();
        $invoice->createNew($this, $externalId);
        if($this->hasValidVoucher()){
            $this->voucher->consume();
        }

        return $redirect;
    }

    public function getTotal(): int
    {
        $total = 0;
        foreach($this->items as $orderItem){
            $total += $orderItem->getPrice();
        }
        if($this->hasValidVoucher()){
            $total -= $this->voucher->getValue();
        }
        return $total;
    }

    public function getFees(): int
    {
        switch($this->paymentMethod->getValue()){
            case PaymentMethod::PREPAYMENT:
                $paymentMethod = new Prepayment();
                break;
            case PaymentMethod::STRIPE:
                $paymentMethod = new Stripe();
                break;
            case PaymentMethod::PAYPAL:
                $paymentMethod = new PayPal();
                break;
            case PaymentMethod::CRYPTO:
                $paymentMethod = new Crypto();
                break;
        }

        return $paymentMethod->getFees($this->getTotal());
    }

    #[Pure]
    public function hasValidVoucher(): bool
    {
        return !is_null($this->voucher) && $this->voucher->isValid();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return Voucher|null
     */
    public function getVoucher(): ?Voucher
    {
        return $this->voucher;
    }


}