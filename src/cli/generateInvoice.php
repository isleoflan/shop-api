<?php

use IOL\Shop\v1\Entity\Invoice;
use IOL\Shop\v1\Entity\Order;
use IOL\Shop\v1\Exceptions\IOLException;

$basePath = __DIR__;
for ($returnDirs = 0; $returnDirs < 1; $returnDirs++) {
    $basePath = substr($basePath, 0, strrpos($basePath, '/'));
}


require_once $basePath . '/_loader.php';

$orderId = $argv[1] ?? false;

if(!$orderId){
    die("No Order ID given");
}

try{
    $order = new Order(id: $orderId);
} catch (IOLException $e){
    die($e->getMessage());
}

$invoice = new Invoice();
$invoice->createNew($order, '');
$path = $invoice->generatePDF();

\IOL\Shop\v1\Request\APIResponse::getInstance()->addData('path', $path);

