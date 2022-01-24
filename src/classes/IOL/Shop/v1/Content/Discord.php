<?php

namespace IOL\Shop\v1\Content;

use IOL\Shop\v1\DataSource\Environment;

class Discord
{
    public static function sendWebhook(array $data): void
    {
        $data = json_encode($data);

        $discordRequest = curl_init(Environment::get('DISCORD_WEBHOOK_URL'));
        $headers = ['Content-Type: application/json'];

        curl_setopt($discordRequest, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($discordRequest, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($discordRequest, CURLOPT_POST, true);
        curl_setopt($discordRequest, CURLOPT_POSTFIELDS, $data);
        curl_exec($discordRequest);
    }
}