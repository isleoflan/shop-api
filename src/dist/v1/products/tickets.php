<?php

    $client = new \IOL\SSO\SDK\Client('253051de-50b6-445f-8486-f60425dc5651');
    $client->setAccessToken('eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE2NDE1MTUxMjgsInNlcyI6IjI3Yzg5M2RkLTgzNGItNGI0Ny05OGJhLWMzZmI4MDE0N2IyNSJ9.txmFxfxYcxrvG9P2cYtcjUQPROGf4wTkrk2_1id0TYkN6i23L9kGsNVJcGvlJ2F5AJKPpXpr5dIk4UyeBi8YXw0hDXZGzDLLfIF8c5_RGWSwVdwwjgQVnw-Y5NM31l7Ei0nrMK9sYWNN5D3jgCIDTgpPHDYobRhe4WqNnBkEtKj-bm4bQIz72CQijsVrQp6mp0j2G0ZOdzAe8vxzJcIEfgGh8WkL2msyKP9l1zapG9EeOnqVKi-sgmmGmT1yYshIocDcSV2WYKKJ6FWrUWY8-Ekj1qqbAINe1oJwhxnIGi5xvv5bRIBfaCCXsl1XwvIrBMlevdPAh5sBzIB9hOZMHpnqq1uB7TvtcKA3bF5DB5LNrsyjfWnVXZB6BaA3mpLJ8-nox3b2DvEPzVFKjpwWfYbkoi4UAyIdHsV44yp2ywwrf8XxZ_2zUlk3sn0_bDMi0u8nQHeRZEOIxKGrKvX2Hz2ujP3286n3wU0mbT3mLt3W1DXp3JZLLAnuXE9VdoE7YFI1EV3T-OgTRzKA-FWHiZZCBceLqtp5PfL8ipv_XbhZG3viBDA8bVcf2HfaJYZT0NlEWA6sgiwxap0-tz8iuewFM6KelSoAND0rrofyMR3qzwa5DaAKiLkJqk_F6rhHx5fxuj0lK0PXkAVaqzE66L_LcoEl54EyI68dR2yRyK8');

    $userDetails = new \IOL\SSO\SDK\Service\User($client);

    var_dump($userDetails->getUserInfo());
