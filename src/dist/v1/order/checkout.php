<?php

    declare(strict_types=1);

    use IOL\Shop\v1\BitMasks\RequestMethod;
    use IOL\Shop\v1\Request\APIResponse;

    $response = APIResponse::getInstance();

    $response->setAllowedRequestMethods(
        new RequestMethod(RequestMethod::POST)
    );
    $response->needsAuth(true);

    $response->check();
    $input = $response->getRequestData([
       [
           'name'      => 'token',
           'types'     => ['string'],
           'required'  => true,
           'errorCode' => 301001,
       ],
   ]);
