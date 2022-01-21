<?php

declare(strict_types=1);

use CoinbaseCommerce\Webhook;
use IOL\Shop\v1\DataSource\Environment;

error_reporting(E_ALL);


$payload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

$headerName = 'X-Cc-Webhook-Signature';
$headers = getallheaders();


try {
    /** @var \CoinbaseCommerce\Resources\Event $event */
    $event = Webhook::buildEvent(trim(file_get_contents('php://input')), $headers[$headerName] ?? null, Environment::get("COINBASE_SECRET"));
    http_response_code(200);

    switch($event->type){
        case 'charge:confirmed':
        case 'charge:delayed':
        $orderId = $event->data->description;

        throw new Exception($orderId);

        try {
            $order = new \IOL\Shop\v1\Entity\Order($orderId);
        } catch (\IOL\Shop\v1\Exceptions\IOLException) {}

        $invoice = new \IOL\Shop\v1\Entity\Invoice();
        $invoice->getForOrder($order);

        $invoice->createPayment($invoice->getValue());
        $order->sendConfirmationMail();

        $order->completeOrder();
    }

} catch (\Exception $exception) {
    echo $exception->getMessage();
    echo 'Failed';
}


$object = $event->data->object;

if($event->type === 'checkout.session.completed') {
        /** @var $object \Stripe\Checkout\Session */

        if($object->status === 'complete') {
            $orderId = $object->client_reference_id;

            try {
                $order = new \IOL\Shop\v1\Entity\Order($orderId);
            } catch (\IOL\Shop\v1\Exceptions\IOLException) {}

            $invoice = new \IOL\Shop\v1\Entity\Invoice();
            $invoice->getForOrder($order);

            $invoice->createPayment($invoice->getValue());
            $order->sendConfirmationMail();

            $order->completeOrder();
        }
}

http_response_code(200);