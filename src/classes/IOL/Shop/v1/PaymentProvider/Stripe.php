<?php

declare(strict_types=1);

namespace IOL\Shop\v1\PaymentProvider;

use IOL\Shop\v1\DataSource\Environment;
use IOL\Shop\v1\Entity\Order;
use IOL\Shop\v1\Entity\OrderItem;
use IOL\Shop\v1\Request\APIResponse;
use IOL\SSO\SDK\Client;
use IOL\SSO\SDK\Service\Authentication;
use IOL\SSO\SDK\Service\User;
use Stripe\Checkout\Session;

class Stripe extends PaymentProvider implements PaymentProviderInterface
{
    public int $fixedFee = 30;
    public float $variableFee = 0.029;

    private Session $session;

    public function getPaymentLink(): ?string
    {
        return $this->session->url;
    }

    public function createPayment(Order $order): string
    {
        $items = [];

        /** @var OrderItem $item */
        foreach($order->getItems() as $item) {
            $tempItems = [
                'name' => $item->getProduct()->getPaymentTitle(),
                'amount' => $item->getPrice(),
                'currency' => 'chf',
                'quantity' => $item->getAmount()
            ];
            if ($item->getProduct()->getPaymentDescription() != '') {
                $tempItems['description'] = $item->getProduct()->getPaymentDescription();
            }

            $items[] = $tempItems;
        }

        $surcharge = $order->getFees();

        $items[] = [
            'name' => 'Zahlungsart Aufschlag',
            'description' => 'Aufschlag für Online-Zahlung',
            'amount' => $surcharge,
            'currency' => 'chf',
            'quantity' => 1
        ];

        $ssoClient = new Client(APIResponse::APP_TOKEN);
        $ssoClient->setAccessToken(APIResponse::getAuthToken());
        $user = new User($ssoClient);
        $userData = $user->getUserInfo();
        $userData = $userData['response']['data'];

        \Stripe\Stripe::setApiKey(Environment::get('STRIPE_SECRET'));

        $this->session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'client_reference_id' => $order->getId(),
            'line_items' => [$items],
            'customer_email' => $userData['email'],
            'success_url' => Environment::get('SUCCESS_URL').'?stsid={CHECKOUT_SESSION_ID}&oid='.$order->getId(),
            'cancel_url' => Environment::get('CANCEL_URL').'?oid='.$order->getId(),
        ]);

        return $this->session->id;
    }

    public function initializeDocuments(Order $order): void
    {
        // TODO: Implement initializeDocuments() method.
    }
}