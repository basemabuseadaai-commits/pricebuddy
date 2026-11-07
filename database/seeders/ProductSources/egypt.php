<?php

use App\Enums\ProductSourceStatus;
use App\Enums\ProductSourceType;
use App\Enums\ScraperService;

/*
 * Search pages for Egyptian stores, so products can be found by keyword.
 *
 * Load with:  php artisan buddy:create-product-sources egypt
 * Shops link to products with relative URLs, so product_url prepends the site.
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
            // The image alt is the only full title (brand + name); the h2 splits them.
            'product_title' => ['type' => 'selector', 'value' => 'img.s-image|alt'],
            'product_url' => ['type' => 'selector', 'value' => 'a.a-link-normal[href*="/dp/"]|href', 'prepend' => 'https://www.amazon.eg'],
            'product_price' => ['type' => 'selector', 'value' => '.a-price > .a-offscreen'],
        ],
        'settings' => [
            'scraper_service' => ScraperService::Http->value,
            // Brand filter by id; the id is read from the brand list on the results page.
            // (p_89:<name> is silently ignored for some brands.)
            'brand_param' => '&rh=p_123:{brand}',
            'brand_lookup' => [
                'pattern' => '~id="p_123/(?<value>\d+)".*?<span class="a-size-base[^"]*"[^>]*>(?<name>[^<]+)</span>~s',
            ],
        ],
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
            'product_url' => ['type' => 'selector', 'value' => 'a[href*="/p/"]|href', 'prepend' => 'https://www.noon.com'],
            // The whole price box also holds the old price and the % off.
            'product_price' => ['type' => 'selector', 'value' => '[data-qa="plp-product-box-price"] [class*="sellingPrice"] strong'],
        ],
        'settings' => [
            'scraper_service' => ScraperService::Api->value,
            // The brand is a path segment: lowercase, spaces as underscores.
            'brand_search_url' => 'https://www.noon.com/egypt-en/{brand}/?q=:search_term',
            'brand_format' => 'lower_underscore',
        ],
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
            'product_url' => ['type' => 'selector', 'value' => 'a[href*="/p/"]|href', 'prepend' => 'https://www.carrefouregypt.com'],
            'product_price' => ['type' => 'selector', 'value' => 'div.items-baseline span + span'], // :nth-child() isn't supported
        ],
        'settings' => [
            'scraper_service' => ScraperService::Api->value,
            // The brand must be spelled exactly as Carrefour does, so it is looked up
            // in the filter list embedded in the results page.
            'brand_param' => '&filter=brand_name%3A%27{brand}%27',
            'brand_lookup' => [
                'pattern' => '~\\\\?"code\\\\?":\\\\?"\'(?<name>[^\'"\\\\]+)\'~',
            ],
        ],
    ],

    // Checked from Egypt on 2026-11-07 with the app's own parser (search pages only).
    [
        'name' => 'Hyper One',
        'store' => 'hyper-one',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        'search_url' => 'https://www.hyperone.com.eg/en/search?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!article.ProductCard'],
            'product_title' => ['type' => 'selector', 'value' => '.product-name'],
            'product_url' => ['type' => 'selector', 'value' => '.ProductCard__Body a|href', 'prepend' => 'https://www.hyperone.com.eg'],
            'product_price' => ['type' => 'selector', 'value' => '.currency'],
        ],
        'settings' => [
            'scraper_service' => ScraperService::Api->value,
            // Products only appear about 8 seconds after the page loads.
            'scraper_options' => ['sleep' => 8000],
        ],
    ],
    [
        'name' => 'LC Waikiki',
        'store' => 'lc-waikiki-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        'search_url' => 'https://www.lcwaikiki.eg/en/search?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!div.product-card'],
            'product_title' => ['type' => 'selector', 'value' => '.product-description'],
            'product_url' => ['type' => 'selector', 'value' => 'a.link|href', 'prepend' => 'https://www.lcwaikiki.eg'],
            // Shelf price. Some cards also show a lower "in basket" price in .price-in-cart.
            'product_price' => ['type' => 'selector', 'value' => '.current-price'],
        ],
        'settings' => ['scraper_service' => ScraperService::Api->value],
    ],
    [
        'name' => 'DeFacto',
        'store' => 'defacto-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        // Titles come back in Arabic.
        'search_url' => 'https://www.defacto.com.eg/search?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!div.product-card'],
            'product_title' => ['type' => 'selector', 'value' => '.product-card__title--name'],
            'product_url' => ['type' => 'selector', 'value' => 'a.product-card__title--name|href', 'prepend' => 'https://www.defacto.com.eg'],
            'product_price' => ['type' => 'selector', 'value' => '.base-price'],
        ],
        'settings' => ['scraper_service' => ScraperService::Http->value],
    ],
    [
        'name' => 'American Eagle',
        'store' => 'american-eagle-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        'search_url' => 'https://www.americaneagle.com.eg/en/search?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!div.product-item.card'],
            'product_title' => ['type' => 'selector', 'value' => '.product-item-title'],
            'product_url' => ['type' => 'selector', 'value' => 'a.product-item-title|href', 'prepend' => 'https://www.americaneagle.com.eg'],
            'product_price' => ['type' => 'selector', 'value' => '.item-price'],
        ],
        'settings' => ['scraper_service' => ScraperService::Api->value],
    ],
    [
        'name' => 'Faces',
        'store' => 'faces-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        'search_url' => 'https://www.faces.eg/en/search?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!div.js-product-tile'],
            // Brand + name, e.g. "Bourjois Rouge Velvet The Lipstick".
            'product_title' => ['type' => 'selector', 'value' => '.tile-body h2'],
            'product_url' => ['type' => 'selector', 'value' => 'a.product-tile-name-link|href', 'prepend' => 'https://www.faces.eg'],
            'product_price' => ['type' => 'selector', 'value' => '.price .js-price'],
        ],
        // Faces' brand filter (&prefn1=brand&prefv1=<Brand>) only works in a normal browser
        // session, so brands are matched in the title instead.
        'settings' => ['scraper_service' => ScraperService::Http->value],
    ],
    [
        'name' => 'IKEA Egypt',
        'store' => 'ikea-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        'search_url' => 'https://www.ikea.com/eg/en/search/?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!div.plp-mastercard'],
            // The link label is the full name, e.g. "TAGGHAJ, Frying pan, non-stick coating black, 24 cm".
            'product_title' => ['type' => 'selector', 'value' => 'a.plp-price-module__product-link|aria-label'],
            'product_url' => ['type' => 'selector', 'value' => 'a.plp-price-module__product-link|href'],
            'product_price' => ['type' => 'selector', 'value' => '.plp-price-module__current-price .plp-price__integer'],
        ],
        'settings' => ['scraper_service' => ScraperService::Api->value],
    ],
    [
        'name' => 'Raneen',
        'store' => 'raneen',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        'search_url' => 'https://www.raneen.com/en/catalogsearch/result/?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!article.product-item'],
            'product_title' => ['type' => 'selector', 'value' => 'a.product-item-link'],
            'product_url' => ['type' => 'selector', 'value' => 'a.product-item-link|href'],
            'product_price' => ['type' => 'selector', 'value' => '[data-price-type=finalPrice] .price'],
        ],
        'settings' => ['scraper_service' => ScraperService::Api->value],
    ],
];
