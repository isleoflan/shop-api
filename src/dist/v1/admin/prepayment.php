<?php

declare(strict_types=1);

use Genkgo\Camt\Config;
use Genkgo\Camt\Reader;
use IOL\Shop\v1\BitMasks\RequestMethod;
use IOL\Shop\v1\Exceptions\IOLException;
use IOL\Shop\v1\Request\APIResponse;
use IOL\SSO\SDK\Client;
use IOL\SSO\SDK\Service\User;

$response = APIResponse::getInstance();

$response->setAllowedRequestMethods(
    new RequestMethod(RequestMethod::POST)
);
$response->needsAuth(true);

$userID = $response->check();

$ssoClient = new Client(APIResponse::APP_TOKEN);
$ssoClient->setAccessToken(APIResponse::getAuthToken());
$user = new User($ssoClient);
$userData = $user->getUserInfo();
$userData = $userData['response']['data'];

if ($userData['scope'] != 8) {
    $response->addError(991999)->render();
}

$reader = new Reader(Config::getDefault());
$message = $reader->readFile($_FILES['file']['tmp_name']);
$statements = $message->getRecords();
foreach ($statements as $statement) {
    $entries = $statement->getEntries();
    foreach ($entries as $entry) {
        foreach ($entry->getTransactionDetails() as $transactionDetails) {
            $amount = $transactionDetails->getAmount()->getAmount();
            $reference = $transactionDetails->getRemittanceInformation()->getCreditorReferenceInformation()->getRef();


            try {
                $invoice = new \IOL\Shop\v1\Entity\Invoice(reference: $reference);
            } catch (IOLException) {
                $response->addError(991001)->render();
            }

            $invoice->createPayment((int)$amount);

            $order = $invoice->getOrder();
            $order->sendPaymentMail((int)$amount, $invoice);

            if ($invoice->isFullyPayed()) {
                $order->completeOrder();
            }
        }
    }
}