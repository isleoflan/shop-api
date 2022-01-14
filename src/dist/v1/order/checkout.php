<?php

    declare(strict_types=1);

    use IOL\Shop\v1\BitMasks\RequestMethod;
    use IOL\Shop\v1\Request\APIResponse;

    $response = APIResponse::getInstance();

    $response->setAllowedRequestMethods(
        new RequestMethod(RequestMethod::POST)
    );
    $response->needsAuth(true);

    $userID = $response->check();
    $input = $response->getRequestData([
                                           [
                                               'name'      => 'user',
                                               'types'     => ['array'],
                                               'required'  => true,
                                               'errorCode' => 301001,
                                           ],
                                           [
                                               'name'      => 'cart',
                                               'types'     => ['array'],
                                               'required'  => true,
                                               'errorCode' => 301001,
                                           ],
                                           [
                                               'name'      => 'paymentType',
                                               'types'     => ['string'],
                                               'required'  => true,
                                               'errorCode' => 301001,
                                           ],
   ]);
