<?php

declare(strict_types=1);

use IOL\Shop\v1\BitMasks\RequestMethod;
use IOL\Shop\v1\Entity\Order;
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
        'name' => 'user',
        'types' => ['array'],
        'required' => true,
        'errorCode' => 601101,
    ],
    [
        'name' => 'cart',
        'types' => ['array'],
        'required' => true,
        'errorCode' => 601102,
    ],
    [
        'name' => 'paymentType',
        'types' => ['string'],
        'required' => true,
        'errorCode' => 601103,
    ],
    [
        'name' => 'voucher',
        'types' => ['string'],
        'required' => false,
        'errorCode' => 601104,
    ],
    [
        'name' => 'username',
        'types' => ['string'],
        'required' => true,
        'errorCode' => 601105,
    ],
]);

foreach (
    [
        'gender' => 601001,
        'forename' => 601002,
        'lastname' => 601003,
        'email' => 601004,
        'address' => 601005,
        'zipCode' => 601006,
        'city' => 601007,
        'vegetarian' => 601008,
    ] as $userField => $errorCode
) {
    if (!isset($input['user'][$userField])) {
        $response->addError($errorCode);
    }
}


if(count($input['cart']) === 0){
    $response->addError(601011);
}
foreach ($input['cart'] as $cartItem) {
    foreach ([
                 'id' => 601008,
                 'amount' => 601009,
                 //'variant' => 601010,
             ] as $itemField => $errorCode) {
        if (!isset($cartItem[$itemField])) {
            $response->addError($errorCode);
        }
    }
}

try {
    $paymentMethod = new \IOL\Shop\v1\Enums\PaymentMethod($input['paymentType']);
} catch (IOLException $e) {
    $response->addError(601103);
}

$order = new Order();
if($order->loadForUser($userID)){
    if($order->hasTicket()){
        $response->addError(601104);
    }
}


if ($response->hasErrors()) {
    $response->render();
}


// TODO: update user data via SSO API
$voucher = null;
if(isset($input['voucher']) && $input['voucher'] !== ''){
    $voucher = new Voucher($input['voucher']);
}


$order = new \IOL\Shop\v1\Entity\Order();
$redirect = $order->createNew($userID, $input['cart'], $paymentMethod, $input['username'], $input['user'], $voucher);

$response->addData('redirect', $redirect);
