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

$userID = $response->check();
$input = $response->getRequestData([
    [
        'name' => 'userId',
        'types' => ['string'],
        'required' => true,
        'errorCode' => 601202,
    ],
]);

$order = new \IOL\Shop\v1\Entity\Order();

try {
    $response->addData('hasOrder', $order->loadForUser($input['userId']));
} catch (IOLException) {
    $response->addError(601202)->render();
}