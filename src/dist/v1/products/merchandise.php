<?php

declare(strict_types=1);

use IOL\Shop\v1\BitMasks\RequestMethod;
use IOL\Shop\v1\Entity\Product;
use IOL\Shop\v1\Request\APIResponse;

$response = APIResponse::getInstance();

$response->setAllowedRequestMethods(
    new RequestMethod(RequestMethod::GET)
);
$response->needsAuth(true);
$userID = $response->check();

$category = new \IOL\Shop\v1\Entity\Category(4);
$category->loadProducts();

$data = [];

/** @var Product $product */
foreach($category->getProducts() as $product){
    $data[] = [
        'id' => $product->getId(),
        'images' => $product->getImages(),
        'title' => $product->getTitle(),
        'description' => $product->getDescription(),
        'price' => $product->getPrice(),
        'variants' => $product->getVariants(),
    ];

    $response->setData($data);
}
