<?php

declare(strict_types=1);

namespace IOL\Shop\v1\PaymentProvider;

use IOL\Shop\v1\DataSource\Environment;
use IOL\Shop\v1\Entity\Order;
use IOL\Shop\v1\Entity\OrderItem;
use IOL\Shop\v1\Request\APIResponse;
use IOL\SSO\SDK\Client;
use IOL\SSO\SDK\Service\User;

class PayPal extends PaymentProvider implements PaymentProviderInterface
{
    public int $fixedFee = 55;
    public float $variableFee = 0.034;

    private string $redirect;

    public function getPaymentLink(): string
    {
        return $this->redirect;
    }

    public function initializeDocuments(Order $order): void
    {
        // TODO: Implement initializeDocuments() method.
    }

    public function createPayment(Order $order): string
    {
        $apiContext = new \PayPal\Rest\ApiContext(new \PayPal\Auth\OAuthTokenCredential(Environment::get('PAYPAL_ID'), Environment::get('PAYPAL_SECRET')));

        if (Environment::get('PAYMENT_MODE') == 'live'){
            $apiContext->setConfig(['mode' => 'live']);
        }

        $ssoClient = new Client(APIResponse::APP_TOKEN);
        $ssoClient->setAccessToken(APIResponse::getAuthToken());
        $user = new User($ssoClient);
        $userData = $user->getUserInfo();
        $userData = $userData['response']['data'];

        $payerInfo = new \PayPal\Api\PayerInfo();
        $payerInfo->setEmail($userData['email']);
        $payerInfo->setFirstName($userData['forename']);
        $payerInfo->setLastName($userData['lastname']);

        $payer = new \PayPal\Api\Payer();
        $payer->setPaymentMethod('paypal');
        $payer->setPayerInfo($payerInfo);

        $items = [];

        /** @var OrderItem $tempItem */
        foreach($order->getItems() as $tempItem) {
            $item = new \PayPal\Api\Item();
            $item->setName($tempItem->getProduct()->getPaymentTitle());
            $item->setDescription($tempItem->getProduct()->getPaymentDescription());
            $item->setCurrency('CHF');
            $item->setQuantity($tempItem->getProduct()->getCategory()->getId() == 3 ? 1 : $tempItem->getAmount());
            //$item->setSku($i->getProduct()->getId());
            $item->setPrice(number_format(($tempItem->getProduct()->getCategory()->getId() == 3 ? $tempItem->getPrice() : $tempItem->getProduct()->getPrice()) / 100,2,".",''));

            $items[] = $item;
        }


        if($order->hasValidVoucher()){
            $item = new \PayPal\Api\Item();
            $item->setName('Rabattcode');
            $item->setDescription('');
            $item->setCurrency('CHF');
            $item->setQuantity(1);
            $item->setPrice(number_format((($order->getVoucher()->getValue() * -1)) / 100,2,".",''));
        }

        $item = new \PayPal\Api\Item();
        $item->setName('Zahlungsart Aufschlag');
        $item->setDescription('Aufschlag für Online-Zahlung');
        $item->setCurrency('CHF');
        $item->setQuantity(1);
        $item->setPrice(number_format(($order->getFees()) / 100,2,".",''));

        $items[] = $item;


        $itemList = new \PayPal\Api\ItemList();
        $itemList->setItems($items);


        $details = new \PayPal\Api\Details();
        $details->setSubtotal(number_format(($order->getTotal() + $order->getFees()) / 100,2,".",''));



        $amount = new \PayPal\Api\Amount();
        $amount->setTotal(number_format(($order->getTotal() + $order->getFees()) / 100,2,".",''));
        $amount->setCurrency('CHF');
        $amount->setDetails($details);

        $transaction = new \PayPal\Api\Transaction();
        $transaction->setItemList($itemList);
        $transaction->setAmount($amount);
        $transaction->setInvoiceNumber($order->getId());

        $redirectUrls = new \PayPal\Api\RedirectUrls();
        $redirectUrls->setReturnUrl(Environment::get('SUCCESS_URL').'?oid='.$order->getId());
        $redirectUrls->setCancelUrl(Environment::get('CANCEL_URL').'?oid='.$order->getId());

        $payment = new \PayPal\Api\Payment();
        $payment->setIntent('sale');
        $payment->setPayer($payer);
        $payment->setTransactions([$transaction]);
        $payment->setRedirectUrls($redirectUrls);


        try {
            $payment->create($apiContext);
            $this->redirect = $payment->getApprovalLink();

            return $payment->id;

        } catch(\Exception $e){
            $return_data['status'] = 'error';
            $return_data['data'] = array('errorid' => 9286);
            $return_data['message'] = '';

            // TODO
        }
        return '';
    }
}