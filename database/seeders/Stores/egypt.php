<?php

use App\Enums\ScraperService;

/*
 * Egyptian stores. Prices are in EGP.
 *
 * Load with:  php artisan buddy:create-stores egypt
 *
 * Stores whose product pages carry Schema.org data use the `schema_org` strategy,
 * which survives page redesigns better than CSS selectors. Shops not listed here
 * (LC Waikiki, Defacto, American Eagle, Colin's, Faces, Hyper One) are auto-created
 * the first time a product URL from them is added, then tuned if needed.
 * Quick-delivery apps (Amazon Now, noon Minutes, Breadfast, Rabbit, talabat mart)
 * price by delivery area and are handled separately.
 */

$egp = [
    'locale_settings' => [
        'locale' => 'en', // English number format ("1,299.00"); pages are read in English.
        'currency' => 'EGP',
    ],
];

$schemaOrg = [
    'title' => ['type' => 'schema_org', 'value' => null],
    'price' => ['type' => 'schema_org', 'value' => null],
    'image' => ['type' => 'schema_org', 'value' => null],
    'availability' => ['type' => 'schema_org', 'value' => null],
];

return [
    // Marketplaces.
    [
        'name' => 'Amazon EG',
        'initials' => 'AZ',
        'slug' => 'amazon-eg',
        'domains' => [
            ['domain' => 'amazon.eg'],
            ['domain' => 'www.amazon.eg'],
        ],
        'scrape_strategy' => [
            'title' => [
                'value' => '#productTitle',
                'type' => 'selector',
            ],
            'price' => [
                'value' => '.a-price > .a-offscreen',
                'type' => 'selector',
            ],
            'image' => [
                'value' => '~"hiRes":"(.+?)"~',
                'type' => 'regex',
            ],
        ],
        'settings' => $egp,
    ],
    [
        'name' => 'noon EG',
        'initials' => 'NO',
        'slug' => 'noon-eg',
        'domains' => [
            ['domain' => 'noon.com'],
            ['domain' => 'www.noon.com'],
        ],
        'scrape_strategy' => $schemaOrg,
        'settings' => array_merge($egp, [
            'scraper_service' => ScraperService::Api->value,
            'scraper_service_settings' => '',
        ]),
    ],
    [
        'name' => 'Jumia EG',
        'initials' => 'JU',
        'slug' => 'jumia-eg',
        'domains' => [
            ['domain' => 'jumia.com.eg'],
            ['domain' => 'www.jumia.com.eg'],
        ],
        'scrape_strategy' => $schemaOrg,
        'settings' => $egp,
    ],

    // Hypermarkets.
    [
        'name' => 'Carrefour EG',
        'initials' => 'CF',
        'slug' => 'carrefour-eg',
        'domains' => [
            ['domain' => 'carrefouregypt.com'],
            ['domain' => 'www.carrefouregypt.com'],
        ],
        'scrape_strategy' => $schemaOrg,
        'settings' => array_merge($egp, [
            'scraper_service' => ScraperService::Api->value,
            'scraper_service_settings' => '',
        ]),
    ],

    // Home (set to "Later" in the shop list, kept ready).
    [
        'name' => 'IKEA EG',
        'initials' => 'IK',
        'slug' => 'ikea-eg',
        'domains' => [
            ['domain' => 'ikea.com'],
            ['domain' => 'www.ikea.com'],
        ],
        'scrape_strategy' => $schemaOrg,
        'settings' => $egp,
    ],
    [
        'name' => 'Raneen',
        'initials' => 'RA',
        'slug' => 'raneen',
        'domains' => [
            ['domain' => 'raneen.com'],
            ['domain' => 'www.raneen.com'],
        ],
        'scrape_strategy' => $schemaOrg,
        'settings' => $egp,
    ],
];
