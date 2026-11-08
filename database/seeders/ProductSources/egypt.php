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
        // Blocks browser-like scrapers with a Cloudflare challenge. Its robots.txt allows bots
        // that identify themselves with a contact, so set BOT_CONTACT (config/scraper_bot.php)
        // and keep the http scraper. Draft until that has been checked.
        'name' => 'Jumia Egypt',
        'store' => 'jumia-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Draft,
        'search_url' => 'https://www.jumia.com.eg/catalog/?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!article.prd'],
            'product_title' => ['type' => 'selector', 'value' => '.name'],
            'product_url' => ['type' => 'selector', 'value' => 'a.core|href', 'prepend' => 'https://www.jumia.com.eg'],
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
            // Sections for saved searches (SavedSearch::CATEGORIES); {brand}/ is dropped without a brand.
            'category_search_urls' => [
                'men_shoes' => 'https://www.noon.com/egypt-en/fashion/men-31225/shoes-17421/{brand}/?q=:search_term',
                'men_sneakers' => 'https://www.noon.com/egypt-en/fashion/men-31225/shoes-17421/fashion-sneakers-20082/{brand}/?q=:search_term',
                'women_shoes' => 'https://www.noon.com/egypt-en/fashion/women-31229/shoes-16238/{brand}/?q=:search_term',
                'women_sneakers' => 'https://www.noon.com/egypt-en/fashion/women-31229/shoes-16238/fashion-sneakers-24738/{brand}/?q=:search_term',
            ],
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
            // The price in basket (applied automatically, no code) when there is one,
            // otherwise the list price. Their schema.org offer uses the same number.
            // The list price only matches when its class is exactly "current-price"
            // (no basket deal). No parentheses: the selector escaping breaks :not().
            'product_price' => ['type' => 'selector', 'value' => ".price-in-cart, span[class='current-price']"],
        ],
        'settings' => ['scraper_service' => ScraperService::Api->value, 'sole_brand' => 'LC Waikiki'],
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
        'settings' => ['scraper_service' => ScraperService::Http->value, 'sole_brand' => 'DeFacto'],
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
        'settings' => ['scraper_service' => ScraperService::Api->value, 'sole_brand' => 'American Eagle'],
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
            // The price you pay. On sale cards the first .js-price is the crossed-out
            // list price; the paid price is always inside .js-price-color. Shade ranges
            // show "from" the cheapest shade.
            'product_price' => ['type' => 'selector', 'value' => '.price .js-price-color .js-price'],
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
        'settings' => ['scraper_service' => ScraperService::Api->value, 'sole_brand' => 'IKEA'],
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

    [
        'name' => 'adidas Egypt',
        'store' => 'adidas-eg',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        // Common searches redirect to a landing page (e.g. /en/black-sneakers); that is fine.
        // Its own titles never say "adidas" (sole_brand), and the card's "Men Running" /
        // "Kids 4-8 Years" line is added to the title so excludes like "kids, women" work.
        'search_url' => 'https://www.adidas.com.eg/en/search?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => '!div.product-tile'],
            'product_title' => ['type' => 'selector', 'value' => '.pdp-link a'],
            'product_url' => ['type' => 'selector', 'value' => '.pdp-link a|href', 'prepend' => 'https://www.adidas.com.eg'],
            'product_price' => ['type' => 'selector', 'value' => '.price .sales .value'],
            // Adds e.g. "Kids 4-8 Years Sportswear" or "Women Running" to the title for filtering.
            'product_title_suffix' => ['type' => 'selector', 'value' => '.gender-division'],
        ],
        'settings' => ['scraper_service' => ScraperService::Api->value, 'sole_brand' => 'adidas'],
    ],

    [
        // No web search: category pages for the Mivida store (aid=9289) are read and their
        // items filtered by the search words. Prices are in the page's __NEXT_DATA__ JSON.
        'name' => 'talabat mart',
        'store' => 'talabat-mart',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        'search_url' => 'https://www.talabat.com/egypt/grocery/745090/tmart-tagammoa-5-emaar-mivida?aid=9289',
        'extraction_strategy' => [
            // Unused while settings.browse is set; kept so the source validates.
            'list_container' => ['type' => 'selector', 'value' => "!div[data-testid='grocery-item']"],
            'product_title' => ['type' => 'selector', 'value' => '.truncate-grocery-item'],
            'product_url' => ['type' => 'selector', 'value' => 'a|href'],
            'product_price' => ['type' => 'selector', 'value' => "[data-testid='price'] .currency"],
        ],
        'settings' => [
            'scraper_service' => ScraperService::Http->value,
            'browse' => [
                'script_id' => '__NEXT_DATA__',
                'items_path' => 'props.pageProps.initialState.itemsData.items',
                'title' => 'title',
                'price' => 'price',
                'url_template' => 'https://www.talabat.com/egypt/grocery/745090/tmart-tagammoa-5-emaar-mivida/product/{slug}/s/{sku}?aid=9289',
                'page_count_path' => 'props.pageProps.initialState.itemsData.pageCount',
                'max_pages' => 3,
                // Only sections whose path contains a search word are read (at most 12).
                'base_url' => 'https://www.talabat.com/egypt/grocery/745090/tmart-tagammoa-5-emaar-mivida',
                'query' => 'aid=9289',
                'max_sections' => 12,
                'paths' => require __DIR__.'/talabat-mart-sections.php',
            ],
        ],
    ],
    [
        // Prices are for noon's default Cairo area (the area can't be kept signed out).
        // Class names carry a build hash (r4yxz) and may need updating after noon redeploys.
        'name' => 'noon Minutes',
        'store' => 'noon-minutes',
        'type' => ProductSourceType::OnlineStore,
        'status' => ProductSourceStatus::Active,
        'search_url' => 'https://minutes.noon.com/egypt-en/search/?q=:search_term',
        'extraction_strategy' => [
            'list_container' => ['type' => 'selector', 'value' => "!div[class*='_wrapper_r4yxz']"],
            'product_title' => ['type' => 'selector', 'value' => "[data-testid='product-title']"],
            'product_url' => ['type' => 'selector', 'value' => "a[data-testid^='catalog-product-box-']|href", 'prepend' => 'https://minutes.noon.com'],
            'product_price' => ['type' => 'selector', 'value' => "[data-testid='product-price'] [class*='sellingPrice'] strong"],
        ],
        'settings' => ['scraper_service' => ScraperService::Api->value],
    ],
];
