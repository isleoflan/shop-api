<?php

declare(strict_types=1);

namespace IOL\Shop\v1\Entity;

use IOL\Shop\v1\Content\Mail;
use IOL\Shop\v1\DataSource\Database;
use IOL\Shop\v1\DataSource\Environment;
use IOL\Shop\v1\DataSource\Queue;
use IOL\Shop\v1\DataType\Date;
use IOL\Shop\v1\DataType\Email;
use IOL\Shop\v1\DataType\UUID;
use IOL\Shop\v1\Enums\OrderStatus;
use IOL\Shop\v1\Enums\PaymentMethod;
use IOL\Shop\v1\Enums\QueueType;
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

    private ?Invoice $invoice = null;

    private array $items = [];

    public function __construct(?string $id = null)
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

        if($this->getSubtotal() === 0){
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

        $this->invoice = $invoice;

        if($this->hasValidVoucher()){
            $this->voucher->consume();
        }

        return $redirect;
    }

    public function getSubtotal(): int
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

        return $paymentMethod->getFees($this->getSubtotal());
    }

    public function getTotal(): int
    {
        return $this->getSubtotal() + $this->getFees();
    }

    public function sendConfirmationMail(): void
    {
        $mail = new Mail();
        $mail->setTemplate('register');
        $mail->setReceiver(new Email('stevebitzi@gmail.com'));
        $mail->setSubject('Deine Ticketbestellung für Isle of LAN 2022');
        $mail->addVariable('preheader', '');
        $mail->addVariable('name', 'Testname'); //
        $mail->addVariable('orderid', $this->getId());
        $mail->addVariable('orderdate', $this->created->format("d.m.Y"));
        //$mail->addVariable('orderaddress', implode("<br />",$user->getAddressArray()));
        $mail->addVariable('orderaddress', 'Address goes here');
        $mail->addVariable('cart', $this->getMailCart());
        $mail->addVariable('paymentmethod', $this->paymentMethod->getPrettyValue());

        switch($this->paymentMethod->getValue()){
            case PaymentMethod::PREPAYMENT:
                if($this->getTotal() === 0){
                    $mail->addAttachment($this->generateTicket());
                    $mail->addVariable('paymentdetails','');
                } else {
                    //$mail->addAttachment($this->generateInvoice());
                    //$mail->addVariable('paymentdetails', $this->getMailPaymentInfo().$this->getTwintText());
                }
                break;
            case PaymentMethod::STRIPE:
            case PaymentMethod::PAYPAL:
            case PaymentMethod::CRYPTO:
                $mail->addAttachment($this->generateTicket());
                $mail->addVariable('paymentdetails','');
                break;
        }


        $mailerQueue = new Queue(new QueueType(QueueType::MAILER));
        $mailerQueue->publishMessage(json_encode($mail), new QueueType(QueueType::MAILER));
    }

    #[Pure]
    public function hasValidVoucher(): bool
    {
        return !is_null($this->voucher) && $this->voucher->isValid();
    }

    public function generateTicket(): string
    {
        $ticket = new Ticket();
        $ticket->createNew($this);
        return $ticket->generatePDF();
    }

    public function completeOrder(): void
    {
        $this->orderStatus = new OrderStatus(OrderStatus::FINISHED);
        $database = Database::getInstance();
        $database->where('id', $this->id);
        $database->update(self::DB_TABLE, [
            'status' => $this->orderStatus->getValue()
        ]);
    }

    public function getTwintText(): string
    {
        $return = '';
        $paymentinfo = [
             ['name' => 'Telefonnummer', 'value' => '076 688 33 84'],
             ['name' => 'Empfänger', 'value' => 'Isle of LAN'],
             ['name' => 'Betrag', 'value' => 'CHF '.number_format($this->getTotal() / 100,2,".","'")],
             ['name' => 'Nachricht', 'value' => $this->getId()],
        ];
        foreach($paymentinfo as $info){
            $return .= '<tr>';
            $return .= '<td style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 12px 4px 0;">'.$info['name'].'</td>';
            $return .= '<td class="text-right" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 0 4px 12px;" align="right"><strong style="font-weight: 600;">'.str_replace(" ","&nbsp;",$info['value']).'</strong></td>';
            $return .= '</tr>';
        }

        $return = '<tr><td class="content text-center border-top" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-top-width: 1px; border-top-style: solid; padding: 40px 48px; border: #3e495b;" align="center"><h4 style="font-weight: 600; font-size: 16px; margin: 0 0 .5em;">Zahlungsdetails</h4><table class="table text-left" cellspacing="0" cellpadding="0" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%; text-align: left;">'.$return.'</table></td></tr>';
        return '<tr><td class="content" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 40px 48px;"><p style="margin: 0 0 1em;">Du kannst auch ganz bequem mit TWINT bezahlen. Sende hierzu einfach eine Zahlung an:</p></td></tr>'.$return;
    }

    public function getMailCart(): string
    {
        $return  = '<table class="table" cellspacing="0" cellpadding="0" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%;">';
        $return .= '<tr>';
        $return .= '<th style="text-transform: uppercase; font-weight: 600; color: #9eb0b7; font-size: 12px; padding: 0 0 4px;"></th>';
        $return .= '<th class="text-right" style="text-transform: uppercase; font-weight: 600; color: #9eb0b7; font-size: 12px; padding: 0 0 4px;" align="right">Preis</th>';
        $return .= '</tr>';

        foreach($this->items as $item){
            $return .= '<tr>';
            $return .= '<td class="pl-md w-100p" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; width: 100%; padding: 4px 12px 4px 0;">';
            $return .= '<strong style="font-weight: 600;">'.$item->getProduct()->getPaymentTitle().'</strong><br />';
            $return .= '<span class="text-muted" style="color: #9eb0b7;">'.$item->getProduct()->getPaymentDescription().'</span>';
            $return .= '</td>';
            $return .= '<td class="text-right" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 0 4px 0;" align="right">CHF '.number_format($item->getPrice() / 100,2,".","'").'</td>';
            $return .= '</tr>';
        }


        $fee = $this->getFees();
        if ($fee > 0) {
            $return .= '<tr>';
            $return .= '<td class="pl-md w-100p" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; width: 100%; padding: 4px 12px 4px 0;">';
            $return .= '<strong style="font-weight: 600;">Zahlungsgebühr</strong><br />';
            $return .= '</td>';
            $return .= '<td class="text-right" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 0 4px 0;" align="right">CHF&nbsp;'.number_format($fee / 100,2,".","'").'</td>';
            $return .= '</tr>';

        }

        $return .= '<tr>';
        $return .= '<td class="pl-md w-100p" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; width: 100%; padding: 4px 12px 4px 0;">';
        $return .= '<strong style="font-weight: 600;">TOTAL</strong><br />';
        $return .= '</td>';
        $return .= '<td class="text-right" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 0 4px 0;" align="right"><strong>CHF&nbsp;'.number_format($this->getTotal() / 100,2,".","'").'</strong></td>';
        $return .= '</tr>';


        $return .= '</table>';


        return $return;
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

    /**
     * @return PaymentMethod
     */
    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    /**
     * @return Date
     */
    public function getCreated(): Date
    {
        return $this->created;
    }

    /**
     * @return string
     */
    public function getUserId(): string
    {
        return $this->userId;
    }


}