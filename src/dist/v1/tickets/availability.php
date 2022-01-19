<?php

declare(strict_types=1);

use IOL\Shop\v1\BitMasks\RequestMethod;
use IOL\Shop\v1\Exceptions\IOLException;
use IOL\Shop\v1\Request\APIResponse;

$response = APIResponse::getInstance();

$response->setAllowedRequestMethods(
    new RequestMethod(RequestMethod::GET)
);
$response->needsAuth(false);

$response->setData([
    'total' => 80,
    'sold' => 34,
    'reserved' => 8,
    'free' => 38,
]);