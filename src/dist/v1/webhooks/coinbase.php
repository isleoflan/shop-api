<?php

declare(strict_types=1);

use CoinbaseCommerce\Webhook;
use IOL\Shop\v1\DataSource\Environment;

$headerName = 'X-Cc-Webhook-Signature';
$headers = getallheaders();


try {
    /** @var \CoinbaseCommerce\Resources\Event $event */
    $event = Webhook::buildEvent(trim(file_get_contents('php://input')), $headers[$headerName] ?? null, Environment::get("COINBASE_SHARED_SECRET"));
    http_response_code(200);

    switch($event->type){
        case 'charge:confirmed':
        case 'charge:delayed':
        $orderId = $event->data->description;

        if($orderId == 'Mastering the Transition to the Information Age'){
            $orderId = '2a5d5d81-f7fb-4900-82e9-f5fb3198e4ec';
        }

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
http_response_code(200);