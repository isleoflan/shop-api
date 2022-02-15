<?php

declare(strict_types=1);

use IOL\Shop\v1\BitMasks\RequestMethod;
use IOL\Shop\v1\Entity\Voucher;
use IOL\Shop\v1\Exceptions\IOLException;
use IOL\Shop\v1\Request\APIResponse;

$response = APIResponse::getInstance();

$response->setAllowedRequestMethods(
    new RequestMethod(RequestMethod::GET)
);
$response->needsAuth(true);

$userId = $response->check();

$order = new \IOL\Shop\v1\Entity\Order();
$ticketPayed = false;

try {
    $orderHasTicket = $order->loadForUser($userId);
} catch (IOLException) {}

if($orderHasTicket) {
    try {
        $invoice = new \IOL\Shop\v1\Entity\Invoice();
        $invoice->getForOrder($order);
        if ($invoice->isFullyPayed()){
            $ticketPayed = true;
        }
    } catch (IOLException) {}
}

$response->addData('hasTicket', $ticketPayed);
