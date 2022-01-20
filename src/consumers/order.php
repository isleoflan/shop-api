<?php

declare(strict_types=1);

use IOL\Shop\v1\DataSource\Queue;
use IOL\Shop\v1\Enums\QueueType;

$basePath = __DIR__;
for ($returnDirs = 0; $returnDirs < 1; $returnDirs++) {
    $basePath = substr($basePath, 0, strrpos($basePath, '/'));
}


require_once $basePath . '/_loader.php';

$userQueue = new Queue(new QueueType(QueueType::ALL_ORDER));
$userQueue->addConsumer(
    callback: static function (\PhpAmqpLib\Message\AMQPMessage $message): void {
        echo '[o] New Message on queue "' . QueueType::NEW_ORDER . '": ' . $message->body . "\r\n";

        try {
            $order = new \IOL\Shop\v1\Entity\Order(id: $message->body);
        } catch (Exception $e) {
            // Order can not be found. Reject the message and if this happens the first time, requeue it.
            $message->reject(!$message->isRedelivered());
            echo '[!] Got error: ' . $e->getMessage() . "\r\n";
            return;
        }


        $order->sendConfirmationMail();
        echo '[x] Sent Confirmation Mail for message ' . $message->body . "\r\n\r\n";
        $message->ack();
    },
    type: new QueueType(QueueType::NEW_ORDER)
);



while ($userQueue->getChannel()->is_open()) {
    $userQueue->getChannel()->wait();
}