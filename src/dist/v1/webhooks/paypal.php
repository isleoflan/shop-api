<?php

declare(strict_types=1);

use IOL\Shop\v1\DataSource\Environment;
use PayPal\Api\VerifyWebhookSignature;

error_reporting(E_ALL);

$requestBody = file_get_contents('php://input');
$headers = getallheaders();

$headers = array_change_key_case($headers, CASE_UPPER);
$apiContext = new \PayPal\Rest\ApiContext(new \PayPal\Auth\OAuthTokenCredential(Environment::get('PAYPAL_ID_'.Environment::get('PAYMENT_MODE')), Environment::get('PAYPAL_SECRET_'.Environment::get('PAYMENT_MODE'))));
$signatureVerification = new VerifyWebhookSignature();
$signatureVerification->setAuthAlgo($headers['PAYPAL-AUTH-ALGO']);
$signatureVerification->setTransmissionId($headers['PAYPAL-TRANSMISSION-ID']);
$signatureVerification->setCertUrl($headers['PAYPAL-CERT-URL']);
$signatureVerification->setWebhookId(Environment::get('PAYPAL_WEBHOOK_ID_'.Environment::get('PAYMENT_MODE'))); // Note that the Webhook ID must be a currently valid Webhook that you created with your client ID/secret.
$signatureVerification->setTransmissionSig($headers['PAYPAL-TRANSMISSION-SIG']);
$signatureVerification->setTransmissionTime($headers['PAYPAL-TRANSMISSION-TIME']);

$signatureVerification->setRequestBody($requestBody);
$request = clone $signatureVerification;

try {
    /** @var \PayPal\Api\VerifyWebhookSignatureResponse $output */
    $output = $signatureVerification->post($apiContext);
} catch (Exception $ex) {
    http_response_code(400);
}
if($output->getVerificationStatus() === 'SUCCESS') {
    // signature is valid
    file_put_contents('/var/www/stripe.txt', $requestBody);

    $data = json_decode($requestBody, true);
    if($data['resource']['state'] === 'completed'){

    }
/*
    $orderId = $object->client_reference_id;

    try {
        $order = new \IOL\Shop\v1\Entity\Order($orderId);
    } catch (\IOL\Shop\v1\Exceptions\IOLException) {}

    $invoice = new \IOL\Shop\v1\Entity\Invoice();
    $invoice->getForOrder($order);

    $invoice->createPayment($invoice->getValue());
    $order->sendConfirmationMail();

    $order->completeOrder();
    */
} else {
    http_response_code(400);
}




http_response_code(200);