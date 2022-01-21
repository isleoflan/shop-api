<?php

declare(strict_types=1);

use IOL\Shop\v1\DataSource\Environment;

error_reporting(E_ALL);


$payload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $signatureHeader, Environment::get('STRIPE_WEBHOOK_SECRET_'. Environment::get('PAYMENT_MODE'))
    );
} catch(\UnexpectedValueException $e) {
    // Invalid payload
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    // Invalid signature
    http_response_code(400);
    exit();
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