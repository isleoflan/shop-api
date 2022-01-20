<?php

declare(strict_types=1);

$payload = @file_get_contents('php://input');
$event = null;


$payload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $signatureHeader, \IOL\Shop\v1\DataSource\Environment::get('STRIPE_WEBHOOK_SECRET')
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


switch($event->type){
    case 'checkout.session.completed':
        file_put_contents('/var/www/stripe.txt', var_export($event->data->object, true)."\r\n\r\n");
        break;
}


// Handle the event
echo 'Received unknown event type ' . $event->type;


http_response_code(200);