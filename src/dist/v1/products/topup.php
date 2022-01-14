<?php

    declare(strict_types=1);

    use IOL\Shop\v1\BitMasks\RequestMethod;
    use IOL\Shop\v1\Request\APIResponse;

    $response = APIResponse::getInstance();

    $response->setAllowedRequestMethods(
        new RequestMethod(RequestMethod::GET)
    );
    $response->needsAuth(true);
    $userID = $response->check();

    $category = new \IOL\Shop\v1\Entity\Category(3);
    $category->loadProducts();

    foreach($category->getProducts() as $product){
        $data = [
            'topUpId' => $product->getId(),
        ];

        $response->setData($data);
    }
