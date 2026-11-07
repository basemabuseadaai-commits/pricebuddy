<?php

use App\Enums\ProductSourceStatus;
use App\Enums\ProductSourceType;
use App\Enums\ScraperService;

/*
 * Search pages for Egyptian stores, so products can be found by keyword.
 *
 * Load with:  php artisan buddy:create-product-sources egypt
 * Each entry's `store` is the slug of a store from database/seeders/Stores/egypt.php.
 *
 * Status Draft = selectors are a best guess and must be checked against the live
 * page from an Egyptian connection before switching to Active.
 */

return [
    [
        'name' => 'Amazon Egypt',
        'store' => 'amazon-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        'search_url' => 'https://www.amazon.eg/s?k=:search_term&language=en_AE',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!div[data-component-type="s-search-result"]'],
            'product_title' => ['type' => 'selector', 'value' => 'h2 span'],
            'product_url' => ['type' => 'selector', 'value' => 'a.a-link-normal[href*="/dp/"]|href'],
            'product_price' => ['type' => 'selector', 'value' => '.a-price > .a-offscreen'],
        ],
        'settings' => ['scraper_service' => ScraperService::Http->value],
    ],
    [
        'name' => 'Jumia Egypt',
        'store' => 'jumia-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        'search_url' => 'https://www.jumia.com.eg/catalog/?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!article.prd'],
            'product_title' => ['type' => 'selector', 'value' => '.name'],
            'product_url' => ['type' => 'selector', 'value' => 'a.core|href'],
            'product_price' => ['type' => 'selector', 'value' => '.prc'],
        ],
        'settings' => ['scraper_service' => ScraperService::Http->value],
    ],
    [
        'name' => 'noon Egypt',
        'store' => 'noon-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Draft,
        'search_url' => 'https://www.noon.com/egypt-en/search/?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!a[href*="/p/"]'],
            'product_title' => ['type' => 'selector', 'value' => '[data-qa="product-name"]'],
            'product_url' => ['type' => 'selector', 'value' => 'a|href'],
        ],
        'settings' => ['scraper_service' => ScraperService::Api->value],
    ],
    [
        'name' => 'Carrefour Egypt',
        'store' => 'carrefour-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Draft,
        'search_url' => 'https://www.carrefouregypt.com/mafegy/en/search?keyword=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!a[href*="/p/"]'],
            'product_title' => ['type' => 'selector', 'value' => 'img|alt'],
            'product_url' => ['type' => 'selector', 'value' => 'a|href'],
        ],
        'settings' => ['scraper_service' => ScraperService::Api->value],
    ],
];
