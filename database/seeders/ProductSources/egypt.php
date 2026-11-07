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
 * Status Draft = not working from an Egyptian connection yet. Selectors for Active
 * sources were checked against live pages in November 2026.
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
            'product_title' => ['type' => 'selector', 'value' => 'a h2 span'], // plain `h2 span` returns the brand
            'product_url' => ['type' => 'selector', 'value' => 'a.a-link-normal[href*="/dp/"]|href'],
            'product_price' => ['type' => 'selector', 'value' => '.a-price > .a-offscreen'],
        ],
        'settings' => ['scraper_service' => ScraperService::Http->value],
    ],
    [
        // Blocked by a Cloudflare challenge for both scrapers (tested Nov 2026). Kept as Draft.
        'name' => 'Jumia Egypt',
        'store' => 'jumia-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Draft,
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
        'status' => ProductSourceStatus::Active,
        'search_url' => 'https://www.noon.com/egypt-en/search/?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '![data-qa="plp-product-box"]'],
            'product_title' => ['type' => 'selector', 'value' => '[data-qa="plp-product-box-name"]'],
            'product_url' => ['type' => 'selector', 'value' => 'a[href*="/p/"]|href'],
            // The whole price box also holds the old price and the % off.
            'product_price' => ['type' => 'selector', 'value' => '[data-qa="plp-product-box-price"] [class*="sellingPrice"] strong'],
        ],
        'settings' => ['scraper_service' => ScraperService::Api->value],
    ],
    [
        'name' => 'Carrefour Egypt',
        'store' => 'carrefour-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        'search_url' => 'https://www.carrefouregypt.com/mafegy/en/search?keyword=:search_term',
        'extraction_strategy' => [
            // Utility-class selectors: may need updating if Carrefour restyles its site.
            'list_container' => ['type' => 'selector', 'value' => '!div.group.rounded-lg.border'],
            'product_title' => ['type' => 'selector', 'value' => 'span.line-clamp-2'],
            'product_url' => ['type' => 'selector', 'value' => 'a[href*="/p/"]|href'],
            'product_price' => ['type' => 'selector', 'value' => 'div.items-baseline span:nth-child(2)'],
        ],
        'settings' => ['scraper_service' => ScraperService::Api->value],
    ],
];
