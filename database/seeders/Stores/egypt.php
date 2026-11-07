<?php

use App\Enums\ScraperService;

/*
 * Egyptian stores. Prices are in EGP.
 *
 * Load with:  php artisan buddy:create-stores egypt
 *
 * Stores whose product pages carry Schema.org data use the `schema_org` strategy,
 * which survives page redesigns better than CSS selectors.
 *
 * Not included (checked Nov 2026): Colin's has no Egyptian web store; Breadfast and
 * Rabbit are app-only; Amazon Now needs a sign-in; noon Minutes timed out; talabat
 * mart's catalogue depends on the delivery area.
 */

$egp = [
    'locale_settings' => [
        'locale' => 'en', // English number format ("1,299.00"); pages are read in English.
        'currency' => 'EGP',
    ],
];

// Shops whose pages are built by JavaScript or that block plain requests.
$browser = array_merge($egp, [
    'scraper_service' => ScraperService::Api->value,
    'scraper_service_settings' => '',
]);

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
                // The first .a-price on the page is often an empty placeholder.
                'value' => '#corePrice_feature_div .a-price .a-offscreen',
                'type' => 'selector',
            ],
            'image' => [
                'value' => '#landingImage|src',
                'type' => 'selector',
            ],
            'availability' => [
                'value' => '#availability span',
                'type' => 'selector',
                'match' => [
                    'out_of_stock' => ['type' => 'regex', 'value' => 'unavailable|out of stock'],
                ],
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
        'settings' => $browser,
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
        'settings' => $browser,
    ],

    [
        'name' => 'Hyper One',
        'initials' => 'H1',
        'slug' => 'hyper-one',
        'domains' => [
            ['domain' => 'hyperone.com.eg'],
            ['domain' => 'www.hyperone.com.eg'],
        ],
        // Pages are built in the browser and fill in slowly. Their Schema.org data has no
        // price, so selectors are used (checked 2026-11-07).
        'scrape_strategy' => [
            'title' => ['type' => 'selector', 'value' => 'h1'],
            'price' => ['type' => 'selector', 'value' => '.currency.highlight'],
            'image' => ['type' => 'selector', 'value' => 'meta[property="og:image"]|content'],
        ],
        'settings' => array_merge($browser, ['scraper_service_settings' => 'sleep=8000']),
    ],

    // Fashion brands. Plain requests get 403, the browser scraper works.
    // Product pages assumed to carry Schema.org data: not checked yet.
    [
        'name' => 'LC Waikiki EG',
        'initials' => 'LC',
        'slug' => 'lc-waikiki-eg',
        'domains' => [
            ['domain' => 'lcwaikiki.eg'],
            ['domain' => 'www.lcwaikiki.eg'],
        ],
        'scrape_strategy' => $schemaOrg,
        'settings' => $browser,
    ],
    [
        'name' => 'DeFacto EG',
        'initials' => 'DF',
        'slug' => 'defacto-eg',
        'domains' => [
            ['domain' => 'defacto.com.eg'],
            ['domain' => 'www.defacto.com.eg'],
        ],
        'scrape_strategy' => $schemaOrg,
        'settings' => $browser,
    ],
    [
        'name' => 'American Eagle EG',
        'initials' => 'AE',
        'slug' => 'american-eagle-eg',
        'domains' => [
            ['domain' => 'americaneagle.com.eg'],
            ['domain' => 'www.americaneagle.com.eg'],
        ],
        'scrape_strategy' => $schemaOrg,
        'settings' => $browser,
    ],

    // Beauty. Product pages carry Schema.org Product data and load with plain requests.
    [
        'name' => 'Faces EG',
        'initials' => 'FA',
        'slug' => 'faces-eg',
        'domains' => [
            ['domain' => 'faces.eg'],
            ['domain' => 'www.faces.eg'],
        ],
        'scrape_strategy' => $schemaOrg,
        'settings' => $egp,
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
