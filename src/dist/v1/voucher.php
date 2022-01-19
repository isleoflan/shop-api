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
        'name' => 'voucher',
        'types' => ['string'],
        'required' => true,
        'errorCode' => 601104,
    ],
]);

if(isset($input['voucher']) && $input['voucher'] !== ''){
    try {
        $voucher = new Voucher($input['voucher']);
    } catch (IOLException) {
        $response->addError(601104)->render();
    }

    if(!$voucher->isValid()){
        $response->addError(601105)->render();
    }

    $response->addData('discount', $voucher->getValue());
}

