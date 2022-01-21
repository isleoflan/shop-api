<?php

declare(strict_types=1);

use IOL\Shop\v1\BitMasks\RequestMethod;
use IOL\Shop\v1\Entity\Voucher;
use IOL\Shop\v1\Exceptions\IOLException;
use IOL\Shop\v1\Request\APIResponse;

$response = APIResponse::getInstance();

$response->setAllowedRequestMethods(
    new RequestMethod(RequestMethod::POST)
);
$response->needsAuth(true);

$userID = $response->check();
$input = $response->getRequestData([
    [
        'name' => 'orderId',
        'types' => ['string'],
        'required' => true,
        'errorCode' => 601201,
    ],
]);

try {
    $order = new \IOL\Shop\v1\Entity\Order($input['orderId']);
} catch(IOLException){
    $response->addError(601201)->render();
}

$order->cancelOrder();