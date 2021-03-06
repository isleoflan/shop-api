<?php

declare(strict_types=1);

namespace IOL\Shop\v1\Entity;

use DateInterval;
use IOL\Shop\v1\Content\Discord;
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
use IOL\Shop\v1\Exceptions\NotFoundException;
use IOL\Shop\v1\PaymentProvider\Crypto;
use IOL\Shop\v1\PaymentProvider\PayPal;
use IOL\Shop\v1\PaymentProvider\Prepayment;
use IOL\Shop\v1\PaymentProvider\Stripe;
use IOL\Shop\v1\Request\APIResponse;
use IOL\SSO\SDK\Client;
use IOL\SSO\SDK\Service\User;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

class Order
{
    public const DB_TABLE = 'orders';

    public const MAX_TICKETS = 80;

    private string $id;
    private string $userId;
    private Date $created;
    private PaymentMethod $paymentMethod;
    private ?Voucher $voucher = null;
    private OrderStatus $orderStatus;

    public string $username;
    public array $userData;

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
        $this->orderStatus = new OrderStatus($values['status']);
        $this->username = $values['username'];
        $this->userData = json_decode($values['userdata'], true);
        $this->loadItems();
    }

    public function loadItems(): void
    {
        $database = Database::getInstance();
        $database->where('order_id', $this->id);
        $database->orderBy('sort');
        foreach($database->get(OrderItem::DB_TABLE) as $itemData){
            $item = new OrderItem();
            $item->loadData($itemData);
            $this->items[] = $item;
        }
    }

    public function hasTicket(): bool
    {
        $ticketCat = new Category(1);
        $ticketCat->loadProducts();
        $ticketIds = [];

        /** @var Product $ticketProduct */
        foreach($ticketCat->getProducts() as $ticketProduct){
            $ticketIds[] = $ticketProduct->getId();
        }

        /** @var OrderItem $item */
        foreach($this->items as $item){
            if(in_array($item->getProduct()->getId(), $ticketIds)){
                return true;
            }
        }

        return false;
    }

    /**
     * @throws NotFoundException
     * @throws InvalidValueException
     */
    public function loadForUser(string $userId): bool
    {
        if(!UUID::isValid($userId)){
            throw new InvalidValueException('Invalid User ID');
        }
        $database = Database::getInstance();
        $data = $database->query('SELECT * FROM '.self::DB_TABLE.' WHERE user_id = \''.$userId.'\' AND (status = "'.OrderStatus::CREATED.'" OR status = \''.OrderStatus::FINISHED.'\') LIMIT 1');
        foreach($data as $orderData){
            $order = new Order();
            $order->loadData($orderData);

            if($order->hasTicket()) {
                return true;
            }
        }
        return false;

    }

    public function createNew(string $userId, array $items, PaymentMethod $paymentMethod, string $username, array $userData, ?Voucher $voucher): string
    {
        $this->id = UUID::newId(self::DB_TABLE);
        $this->userId = $userId;
        $this->created = new Date('u');
        $this->paymentMethod = $paymentMethod;
        $this->voucher = $voucher;
        $this->orderStatus = new OrderStatus(OrderStatus::CREATED);
        $this->userData = $userData;
        $this->username = $username;

        $foodItemsCat = new Category(2);
        $foodItemsCat->loadProducts();

        $specialDealCat = new Category(5);
        $specialDealCat->loadProducts();

        $specialDeal = $specialDealCat->getProducts();
        /** @var Product $specialDeal */
        $specialDeal = $specialDeal[0];

        $foodItems = [];
        /** @var Product $product */
        foreach($foodItemsCat->getProducts() as $product){
            $foodItems[$product->getId()] = false;
        }

        foreach($items as $item){
            if(isset($foodItems[$item['id']])) {
                $foodItems[$item['id']] = true;
            }
        }

        $hasSpecialDeal = true;

        foreach($foodItems as $foodItem){
            if($foodItem === false){ $hasSpecialDeal = false; }
        }

        foreach($items as $sort => $item){
            if($hasSpecialDeal && isset($foodItems[$item['id']])){
                // don't add food item
            } else {
                $orderItem = new OrderItem();
                $orderItem->createNew($this->id, $item, $sort);

                $this->items[] = $orderItem;
            }
        }

        if($hasSpecialDeal){
            $orderItem = new OrderItem();
            $orderItem->createNew($this->id, ['id' => $specialDeal->getId(), 'amount' => 1], 10);

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
            'status' => $this->orderStatus->getValue(),
            'username' => $this->username,
            'userdata' => json_encode($this->userData)
        ]);

        switch($this->paymentMethod->getValue()){
            default:
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

        if($this->paymentMethod->getValue() === PaymentMethod::PREPAYMENT){
            $this->sendConfirmationMail();

            if($this->getTotal() === 0){
                $this->completeOrder();
            }
        }

        $this->sendNewOrderDiscordWebhook();

        return $redirect;
    }

    public function sendNewOrderDiscordWebhook(): void
    {
        $data = [
            'embeds' => [
                [
                    'title'			=> 'Neue Bestellung',
                    'description'	=> 'Im IOL Shop ist eine neue Bestellung eingegangen',
                    'color'			=> '11475628',
                    'fields'		=> [
                        [
                            'name'		=> 'Bestell-Nr',
                            'value'		=> $this->id,
                            'inline'	=> true,
                        ],
                        [
                            'name'		=> 'Gekauft von',
                            'value'		=> $this->username,
                            'inline'	=> true,
                        ],
                        [
                            'name'		=> 'Bezahlt mit',
                            'value'		=> $this->paymentMethod->getPrettyValue(),
                            'inline'	=> true,
                        ],
                        [
                            'name'		=> 'Betrag',
                            'value'		=> 'CHF '.number_format($this->getTotal() / 100,2,'.',"'"),
                            'inline'	=> true,
                        ],
                    ],
                ]
            ]
        ];

        $itemData = [];
        /** @var OrderItem $item */
        foreach($this->items as $item){
            $itemData[] = ($item->getProduct()->getCategory()->getId() == 3 ? 1 : $item->getAmount()).'x '.$item->getProduct()->getTitle();
        }
        $data['embeds'][0]['fields'][] = ['name' => 'Artikel', 'value' => implode("\r\n", $itemData), 'inline' => true];

        Discord::sendWebhook($data);
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
        $paymentMethod = match ($this->paymentMethod->getValue()) {
            PaymentMethod::PREPAYMENT => new Prepayment(),
            PaymentMethod::STRIPE => new Stripe(),
            PaymentMethod::PAYPAL => new PayPal(),
            PaymentMethod::CRYPTO => new Crypto(),
        };

        return $paymentMethod->getFees($this->getSubtotal());
    }

    public function getTotal(): int
    {
        return $this->getSubtotal() + $this->getFees();
    }

    public function sendConfirmationMail(): void
    {
        if($this->orderStatus->getValue() === OrderStatus::CREATED) {
            $mail = new Mail();
            $mail->setTemplate('order');
            $mail->setReceiver(new Email($this->userData['email']));
            $mail->setSubject('Deine Ticketbestellung f??r Isle of LAN 2022');
            $mail->addVariable('preheader', '');
            $mail->addVariable('name', $this->userData['forename']); //
            $mail->addVariable('orderid', $this->getId());
            $mail->addVariable('orderdate', $this->created->format("d.m.Y"));
            $mail->addVariable('orderaddress', implode("<br />",[
                $this->userData['forename'].' '.$this->userData['lastname'],
                $this->userData['address'],
                $this->userData['zipCode'].' '.$this->userData['city']
            ]));
            $mail->addVariable('cart', $this->getMailCart());
            $mail->addVariable('paymentmethod', $this->paymentMethod->getPrettyValue());

            switch ($this->paymentMethod->getValue()) {
                case PaymentMethod::PREPAYMENT:
                    if ($this->getTotal() === 0) {
                        $mail->addAttachment($this->generateTicket());
                        $mail->addVariable('paymentdetails', '');
                    } else {
                        $mail->addAttachment($this->generateInvoice());
                        $mail->addVariable('paymentdetails', $this->getMailPaymentInfo().$this->getTwintText());
                    }
                    $mail->addVariable('seatbutton', '');
                    break;
                case PaymentMethod::STRIPE:
                case PaymentMethod::PAYPAL:
                case PaymentMethod::CRYPTO:
                    $mail->addAttachment($this->generateTicket());
                    $mail->addVariable('paymentdetails', '');
                    $mail->addVariable('seatbutton', $this->getSeatButton());
                    break;
            }


            $mailerQueue = new Queue(new QueueType(QueueType::MAILER));
            $mailerQueue->publishMessage(json_encode($mail), new QueueType(QueueType::MAILER));
        }
    }

    public function getSeatButton(): string
    {
        return '<tr>'.
            '<td class="content" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, '.
            'Helvetica Neue, Helvetica, Arial, sans-serif; padding: 40px 48px;"><table cellspacing="0" cellpadding="0" '.
            'style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, '.
            'Arial, sans-serif; border-collapse: collapse; width: 100%;"><tbody><tr><td align="center" '.
            'style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, '.
            'Arial, sans-serif;"><table cellpadding="0" cellspacing="0" border="0" class="bg-green rounded" '.
            'style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, '.
            'Arial, sans-serif; border-collapse: separate; width: 100%; color: #ffffff; border-radius: 3px;" '.
            'bgcolor="#5eba00"><tbody><tr><td align="center" valign="top" class="lh-1" style="font-family: Open Sans, '.
            '-apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; '.
            'line-height: 100%;"><a href="https://dashboard.isleoflan.ch" class="btn bg-green border-green" '.
            'style="color: #ffffff; padding: 12px 32px; border: 1px solid #5eba00; text-decoration: none; '.
            'white-space: nowrap; font-weight: 600; font-size: 16px; border-radius: 3px; line-height: 100%; '.
            'display: block; -webkit-transition: .3s background-color; transition: .3s background-color; '.
            'background-color: #5eba00;"><span class="btn-span" style="color: #ffffff; font-size: 16px; '.
            'text-decoration: none; white-space: nowrap; font-weight: 600; line-height: 100%;">'.
            'Jetzt Sitzplatz reservieren</span></a></td></tr></tbody></table></td></tr></tbody></table></td></tr>';
    }

    public function sendPaymentMail(int $payedValue, Invoice $invoice): void
    {
        $mail = new Mail();
        $mail->setTemplate('payment');
        $mail->setReceiver(new Email($this->userData['email']));
        $mail->setSubject('Deine Bezahlung f??r Isle of LAN ist angekommen!');
        $mail->addVariable('preheader', 'Danke f??r deine Bezahlung');
        $mail->addVariable('paymentvalue', number_format($payedValue / 100, 2,'.',"'"));
        $mail->addVariable('paymentmethod', $this->paymentMethod->getPrettyValue());
        $mail->addVariable('paymenttext',
            ($invoice->isFullyPayed()) ?
                'Vielen Dank, deine Zahlung ist komplett!'.($this->hasTicket() ? ' Anbei erh??ltst du dein Ticket!' : '') :
                'Bis zur kompletten Zahlung fehlen noch CHF '.number_format(($invoice->getValue() - $invoice->getTotalPayed()) / 100, 2, ".", "'")
        );
        $mail->addVariable('payedpercentage', number_format(($invoice->getTotalPayed() / $invoice->getValue()) * 100, 2,'.',''));
        $mail->addVariable('totalpayed', number_format($invoice->getTotalPayed() / 100, 2,'.',"'"));
        $mail->addVariable('totaldue', number_format(($invoice->getValue() - $invoice->getTotalPayed()) / 100, 2,'.',"'"));

        if($invoice->isFullyPayed()){
            if($this->hasTicket()){
                $mail->addAttachment($this->generateTicket());
                $mail->addVariable('seatbutton', $this->getSeatButton());
            } else {
                $mail->addVariable('seatbutton', '');
            }
        } else {
            $mail->addVariable('seatbutton', '');
        }

        $mailerQueue = new Queue(new QueueType(QueueType::MAILER));
        $mailerQueue->publishMessage(json_encode($mail), new QueueType(QueueType::MAILER));
    }

    public function cancelOrder(): void
    {
        $this->orderStatus = new OrderStatus(OrderStatus::CANCELLED);
        $database = Database::getInstance();
        $database->where('id', $this->id);
        $database->update(self::DB_TABLE, [
            'status' => $this->orderStatus->getValue()
        ]);
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

    private function generateInvoice(): string
    {
        return $this->invoice->generatePDF();
    }

    public function completeOrder(): void
    {
        $this->orderStatus = new OrderStatus(OrderStatus::FINISHED);
        $database = Database::getInstance();
        $database->where('id', $this->id);
        $database->update(self::DB_TABLE, [
            'status' => $this->orderStatus->getValue()
        ]);
        $foodItemsCat = new Category(2);
        $foodItemsCat->loadProducts();

        $specialDealCat = new Category(5);
        $specialDealCat->loadProducts();
        $specialDealID = $specialDealCat->getProducts();
        /** @var Product $specialDealID */
        $specialDealID = $specialDealID[0];

        $topupCat = new Category(3);
        $topupCat->loadProducts();
        $topupId = $topupCat->getProducts();
        /** @var Product $topupId */
        $topupId = $topupId[0];

        $foodItems = [];
        /** @var Product $product */
        foreach($foodItemsCat->getProducts() as $product){
            $foodItems[$product->getId()] = false;
        }


        foreach($this->items as $item){
            /** @var OrderItem $item */
            switch($item->getProduct()->getId()){
                case $topupId->getId():
                    $database->insert('transactions', [
                        'id' => UUID::newId('transactions'),
                        'value' => $item->getPrice() * -1,
                        'user_id' => $this->userId,
                        'time' => Date::now(Date::DATETIME_FORMAT_MICRO)
                    ]);
                    break;
                case $specialDealID->getId():
                    foreach($foodItems as $foodId => $val) {
                        $database->insert('food', [
                            'user_id' => $this->userId,
                            'product_id' => $foodId
                        ]);
                    }
                    break;
            }

            if(array_key_exists($item->getProduct()->getId(), $foodItems)){
                $database->insert('food', [
                    'user_id' => $this->userId,
                    'product_id' => $item->getProduct()->getId()
                ]);
            }
        }

    }

    public function getTwintText(): string
    {
        $return = '';
        $paymentinfo = [
             ['name' => 'Telefonnummer', 'value' => '076 688 33 84'],
             ['name' => 'Empf??nger', 'value' => 'Isle of LAN'],
             ['name' => 'Betrag', 'value' => 'CHF '.number_format($this->getTotal() / 100,2,".","'")],
             ['name' => 'Nachricht', 'value' => $this->invoice->getNumber()],
        ];
        foreach($paymentinfo as $info){
            $return .= '<tr>';
            $return .= '<td style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 12px 4px 0;">'.$info['name'].'</td>';
            $return .= '<td class="text-right" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 0 4px 12px;" align="right"><strong style="font-weight: 600;">'.str_replace(" ","&nbsp;",$info['value']).'</strong></td>';
            $return .= '</tr>';
        }

        $return = '<tr><td class="content text-center border-top" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 40px 48px; border-color: #3e495b;border-top: 1px solid;" align="center"><h4 style="font-weight: 600; font-size: 16px; margin: 0 0 .5em;">Zahlungsdetails</h4><table class="table text-left" cellspacing="0" cellpadding="0" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%; text-align: left;">' .$return.'</table></td></tr>';
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
            $return .= '<strong style="font-weight: 600;">Zahlungsgeb??hr</strong><br />';
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

    private function getMailPaymentInfo(): string
    {
        $return = '';
        $payDate = new Date();
        $payDate->add(new DateInterval("P20D"));

        $paymentInfo = [
            ['name' => 'offener Betrag', 'value' => 'CHF '.number_format($this->getTotal() / 100,2,".","'")],
            ['name' => 'zahlbar bis', 'value' => $payDate->format('d.m.Y')],
            ['name' => 'Kontonummer', 'value' => '01-7702-0'],
            ['name' => 'Bank', 'value' => 'Raiffeisenbank Mittelthurgau<br/>8570 Weinfelden'],
            ['name' => 'Zugunsten von', 'value' => 'Isle of LAN<br/>8574 Illighausen'],
            ['name' => 'Referenznummer', 'value' => $this->invoice->getNiceReference()],
        ];

        foreach($paymentInfo as $info){
            $return .= '<tr>';
            $return .= '<td style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 12px 4px 0;">'.$info['name'].'</td>';
            $return .= '<td class="text-right" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 4px 0 4px 12px;" align="right"><strong style="font-weight: 600;">'.str_replace(' ','&nbsp;',$info['value']).'</strong></td>';
            $return .= '</tr>';
        }

        $return = '<tr><td class="content text-center border-top" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 40px 48px; border-color: #3e495b;border-top: 1px solid;" align="center"><h4 style="font-weight: 600; font-size: 16px; margin: 0 0 .5em;">Zahlungsdetails</h4><table class="table text-left" cellspacing="0" cellpadding="0" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%; text-align: left;">' .$return.'</table></td></tr>';
        return '<tr><td class="content" style="font-family: Open Sans, -apple-system, BlinkMacSystemFont, Roboto, Helvetica Neue, Helvetica, Arial, sans-serif; padding: 40px 48px;"><p style="margin: 0 0 1em;">Damit deine Bestellung definitiv wird, du dein Ticket erh??ltst und einen Sitzplatz reservieren kannst, erwarten wir deine Vorauszahlung bis zum '.$payDate->format("d.m.Y").'. Solltest du weitere Fragen haben, schreib uns eine E-Mail an <a href="mailto:support@isleoflan.ch" style="color: #467fcf; text-decoration: none;">support@isleoflan.ch</a>.<br />Verwende f??r deine Zahlung einen orangen Einzahlungsschein mit folgenden Daten. Bitte drucke den Einzahlungsschein nicht aus, sondern zahle mit E-Banking. Falls du einen gedruckten Einzahlungsschein ben??tigst, melde dich bei uns.</p></td></tr>'.$return;
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

    #[ArrayShape(['total' => "int", 'sold' => "int", 'reserved' => "int", 'free' => "int"])]
    public function getCounts(): array
    {
        $database = Database::getInstance();
        $database->where('status', OrderStatus::CANCELLED, '<>');
        $data = $database->get(self::DB_TABLE);

        $finished = 0;
        $created = 0;

        foreach($data as $orderData){
            $order = new Order();
            $order->loadData($orderData);
            if($order->hasTicket()) {
                switch ($order->getOrderStatus()->getValue()) {
                    case OrderStatus::FINISHED:
                        $finished++;
                        break;
                    case OrderStatus::CREATED:
                        $created++;
                }
            }
        }

        return [
            'total' => self::MAX_TICKETS,
            'sold' => $finished,
            'reserved' => $created,
            'free' => self::MAX_TICKETS - $finished - $created,
        ];
    }

    public function getAttendees(): array
    {
        $database = Database::getInstance();
        $database->where('status', OrderStatus::CANCELLED, '<>');
        $database->orderBy('created', 'DESC');
        $data = $database->get(self::DB_TABLE);

        $return = [];

        $ssoClient = new Client(APIResponse::APP_TOKEN);
        $user = new User($ssoClient);
        $allUsers = $user->getList();
        $allUsers = $allUsers['response']['data'];


        foreach($data as $orderData){
            $order = new Order();
            $order->loadData($orderData);

            $attendee = $allUsers[$order->userId];

            if($order->hasTicket()) {
                switch ($order->getOrderStatus()->getValue()) {
                    case OrderStatus::FINISHED:
                        $attendee['status'] = 'PAYED';
                        break;
                    case OrderStatus::CREATED:
                        $attendee['status'] = 'BOUGHT';
                }
                $return[] = $attendee;
            }
        }

        return $return;
    }

    public function changeToTwint()
    {
        $this->paymentMethod = new PaymentMethod(PaymentMethod::TWINT);
        $database = Database::getInstance();
        $database->where('id', $this->id);
        $database->update(self::DB_TABLE, [
            'payment_method' => $this->paymentMethod->getValue()
        ]);
    }

    /**
     * @return OrderStatus
     */
    public function getOrderStatus(): OrderStatus
    {
        return $this->orderStatus;
    }


}