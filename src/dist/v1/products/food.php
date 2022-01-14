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

    $category = new \IOL\Shop\v1\Entity\Category(2);
    $category->loadProducts();

    $data = [];
    foreach($category->getProducts() as $product){
        $data[] = [
            'id' => $product->getId(),
            'title' => $product->getTitle(),
            'description' => $product->getDescription(),
            'date' => $product->getAdditionalData()['date'],
            'price' => $product->getPrice(),
        ];
    }

    $response->addData('menus', $data);


    $specialDealCategory = new \IOL\Shop\v1\Entity\Category(5);
    $specialDealCategory->loadProducts();

    foreach($specialDealCategory->getProducts() as $product){
        $specialDealData = [
            'id' => $product->getId(),
            'title' => $product->getTitle(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
        ];
    }
    $response->addData('specialDeal', $specialDealData);
