<?php

declare(strict_types=1);

use CoinbaseCommerce\Webhook;
use IOL\Shop\v1\DataSource\Environment;

error_reporting(E_ALL);



$headerName = 'X-Cc-Webhook-Signature';
$headers = getallheaders();


try {
    /** @var \CoinbaseCommerce\Resources\Event $event */
    $event = Webhook::buildEvent(trim(file_get_contents('php://input')), $headers[$headerName] ?? null, Environment::get("COINBASE_SECRET"));
    http_response_code(200);

    throw new Exception(var_export($event, true));
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
http_response_code(200);