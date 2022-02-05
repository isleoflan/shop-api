<?php

declare(strict_types=1);

use AndrewSvirin\Ebics\Contracts\EbicsResponseExceptionInterface;
use AndrewSvirin\Ebics\Services\KeyRingManager;
use AndrewSvirin\Ebics\Models\Bank;
use AndrewSvirin\Ebics\Models\User;
use AndrewSvirin\Ebics\EbicsClient;
use IOL\Shop\v1\DataSource\Environment;
use IOL\Shop\v1\DataSource\File;

$basePath = __DIR__;
for ($returnDirs = 0; $returnDirs < 1; $returnDirs++) {
    $basePath = substr($basePath, 0, strrpos($basePath, '/'));
}


require_once $basePath . '/_loader.php';


$keyRingRealPath = File::getBasePath().'/assets/ebics/ebicsKeyring.json';
$keyRingManager = new KeyRingManager($keyRingRealPath, Environment::get('EBICS_KEYRING_PASSPHRASE'));
$keyRing = $keyRingManager->loadKeyRing();

$bank = new Bank(Environment::get('EBICS_HOST_ID'), Environment::get('EBICS_HOST_URL'), Bank::VERSION_30);
$bank->setIsCertified(false);
$user = new User(Environment::get('EBICS_CONTRACT_ID'), Environment::get('EBICS_SUBSCRIBER_ID'));
$client = new EbicsClient($bank, $user, $keyRing);


try {
    /* @var \AndrewSvirin\Ebics\EbicsClient $client */
    $client->INI();
    /* @var \AndrewSvirin\Ebics\Services\KeyRingManager $keyRingManager */
    /* @var \AndrewSvirin\Ebics\Models\KeyRing $keyRing */
    $keyRingManager->saveKeyRing($keyRing);
} catch (EbicsResponseExceptionInterface $exception) {
    echo sprintf(
        "INI request failed. EBICS Error code : %s\nMessage : %s\nMeaning : %s",
        $exception->getResponseCode(),
        $exception->getMessage(),
        $exception->getMeaning()
    );
}

try {
    $client->HIA();
    $keyRingManager->saveKeyRing($keyRing);
} catch (EbicsResponseExceptionInterface $exception) {
    echo sprintf(
        "HIA request failed. EBICS Error code : %s\nMessage : %s\nMeaning : %s",
        $exception->getResponseCode(),
        $exception->getMessage(),
        $exception->getMeaning()
    );
}

try {
    $client->HPB();
    $keyRingManager->saveKeyRing($keyRing);
} catch (EbicsResponseExceptionInterface $exception) {
    echo sprintf(
        "HPB request failed. EBICS Error code : %s\nMessage : %s\nMeaning : %s",
        $exception->getResponseCode(),
        $exception->getMessage(),
        $exception->getMeaning()
    );
}

/* @var \AndrewSvirin\Ebics\EbicsClient $client */
$ebicsBankLetter = new \AndrewSvirin\Ebics\EbicsBankLetter();

$bankLetter = $ebicsBankLetter->prepareBankLetter(
    $client->getBank(),
    $client->getUser(),
    $client->getKeyRing()
);

$txt = $ebicsBankLetter->formatBankLetter($bankLetter, $ebicsBankLetter->createPdfBankLetterFormatter());
file_put_contents(File::getBasePath().'/assets/ebics/INIletter.pdf', $txt);

echo $txt;