<?php

/*
 * Shops whose robots.txt only allows bots that say who they are (Jumia asks for an
 * owner URL or contact). Requests to these hosts made by the http scraper send an
 * honest User-Agent and From header instead of a browser User-Agent.
 *
 * The contact comes from BOT_CONTACT in the .env file on the PC, never the repo.
 * Without it, nothing changes.
 */
return [
    'contact' => env('BOT_CONTACT'),

    'name' => env('BOT_NAME', 'BSBS-PriceChecker/1.0'),

    'hosts' => array_filter(array_map('trim', explode(',', env('BOT_HOSTS', 'jumia.com.eg')))),
];
