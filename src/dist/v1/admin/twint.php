<?php

declare(strict_types=1);

use IOL\Shop\v1\BitMasks\RequestMethod;
use IOL\Shop\v1\Entity\Voucher;
use IOL\Shop\v1\Exceptions\IOLException;
use IOL\Shop\v1\Request\APIResponse;
use IOL\SSO\SDK\Client;
use IOL\SSO\SDK\Service\User;

$response = APIResponse::getInstance();

$response->setAllowedRequestMethods(
    new RequestMethod(RequestMethod::PATCH)
);
$response->needsAuth(true);

$userID = $response->check();
$input = $response->getRequestData([
    [
        'name' => 'invoiceNumber',
        'types' => ['integer'],
        'required' => true,
        'errorCode' => 991001,
    ],
    [
        'name' => 'value',
        'types' => ['string'],
        'required' => true,
        'errorCode' => 991002,
    ],
]);

$ssoClient = new Client(APIResponse::APP_TOKEN);
$ssoClient->setAccessToken(APIResponse::getAuthToken());
$user = new User($ssoClient);
$userData = $user->getUserInfo();
$userData = $userData['response']['data'];

if($userData['scope'] != 8){
    $response->addError(991999)->render();
}


try {
    $invoice = new \IOL\Shop\v1\Entity\Invoice(number: $input['invoiceNumber']);
} catch(IOLException){
    $response->addError(991001)->render();
}


$invoice->createPayment((int)$input['value']);

$order = $invoice->getOrder();
$order->sendPaymentMail((int)$input['value'], $invoice);

if($invoice->isFullyPayed()) {
    $order->completeOrder();
}