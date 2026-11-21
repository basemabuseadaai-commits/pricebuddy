<?php

namespace Tests\Feature\Services;

use App\Models\ProductSource;
use App\Models\Store;
use App\Services\FirecrawlService;
use App\Services\Helpers\SettingsHelper;
use App\Services\ProductSourceSearchService;
use App\Services\ScrapeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Once;
use Tests\TestCase;
use Yoeriboven\LaravelLogDb\Models\LogMessage;

class FirecrawlFallbackTest extends TestCase
{
    use RefreshDatabase;

    const SHOP_URL = 'https://shop.example.com/product/1';

    const KEY = 'fc-test-secret-key-123456';

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Store::query()->delete();
        config(['services.firecrawl.key' => null, 'services.firecrawl.url' => 'https://api.firecrawl.dev']);

        $this->store = Store::factory()->createOne([
            'domains' => [['domain' => 'shop.example.com']],
            'settings' => [
                'scraper_service' => 'http',
                'scraper_service_settings' => '',
                'firecrawl_fallback' => true,
            ],
        ]);

        $this->setFirecrawl(['enabled' => true, 'api_key' => Crypt::encryptString(self::KEY)]);
    }

    protected function setFirecrawl(array $settings): void
    {
        SettingsHelper::setSetting('integrated_services', ['firecrawl' => $settings]);
        SettingsHelper::$settings = null;
        Once::flush();
    }

    protected function productPage(string $title = 'Kids Crocs', string $price = '1299'): string
    {
        // Padded to the size of a real shop page.
        return View::make('tests.product-page', ['title' => $title, 'price' => $price, 'image' => null])->render()
            .str_repeat('<nav>menu</nav>', 200);
    }

    protected function fakeShopAndFirecrawl(mixed $shopResponse, ?array $firecrawlJson = null, int $firecrawlStatus = 200): void
    {
        Http::fake([
            'api.firecrawl.dev/*' => Http::response($firecrawlJson ?? [
                'success' => true,
                'data' => ['rawHtml' => $this->productPage(), 'metadata' => ['statusCode' => 200]],
            ], $firecrawlStatus),
            'shop.example.com/*' => $shopResponse,
        ]);
    }

    protected function firecrawlCalls(): int
    {
        return count(Http::recorded(fn (Request $r) => str_contains($r->url(), 'api.firecrawl.dev')));
    }

    public function test_blocked_product_page_is_fetched_through_firecrawl(): void
    {
        $this->fakeShopAndFirecrawl(Http::response('Forbidden', 403));

        $scrape = ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false);
        $result = $scrape->scrape();

        $this->assertSame('Kids Crocs', $result['title']);
        $this->assertSame('1299', $result['price']);
        $this->assertSame('firecrawl', $scrape->fetchedVia);
        $this->assertSame(1, $this->firecrawlCalls());

        Http::assertSent(function (Request $r) {
            return str_contains($r->url(), 'api.firecrawl.dev/v2/scrape')
                && $r->hasHeader('Authorization', 'Bearer '.self::KEY)
                && $r['url'] === self::SHOP_URL
                && $r['formats'] === ['rawHtml']
                && $r['location']['country'] === 'EG';
        });
    }

    public function test_store_cookies_are_not_sent_to_firecrawl(): void
    {
        $this->store->update(['cookies' => 'session=abc']);
        $this->fakeShopAndFirecrawl(Http::response('Forbidden', 403));

        ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false)->scrape();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.firecrawl.dev')
            && ! str_contains(json_encode($r->data()).json_encode($r->headers()), 'session=abc'));
        // ...and the key never goes to the shop.
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'shop.example.com')
            && str_contains(json_encode($r->headers()), self::KEY));
    }

    public function test_bot_check_page_triggers_firecrawl(): void
    {
        $this->fakeShopAndFirecrawl(Http::response('<html><head><title>Just a moment...</title></head><body>cf_chl_opt</body></html>'));

        $result = ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false)->scrape();

        $this->assertSame('Kids Crocs', $result['title']);
        $this->assertSame(1, $this->firecrawlCalls());
    }

    public function test_working_shop_does_not_use_firecrawl(): void
    {
        $this->fakeShopAndFirecrawl(Http::response($this->productPage('Normal page', '500')));

        $result = ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false)->scrape();

        $this->assertSame('Normal page', $result['title']);
        $this->assertSame(0, $this->firecrawlCalls());
    }

    public function test_store_that_did_not_opt_in_never_uses_firecrawl(): void
    {
        $this->store->update(['settings' => ['scraper_service' => 'http', 'scraper_service_settings' => '']]);
        $this->fakeShopAndFirecrawl(Http::response('Forbidden', 403));

        ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false)->setLogErrors(false)->scrape();

        $this->assertSame(0, $this->firecrawlCalls());
    }

    public function test_disabled_or_keyless_firecrawl_is_not_used(): void
    {
        $this->fakeShopAndFirecrawl(Http::response('Forbidden', 403));

        $this->setFirecrawl(['enabled' => false, 'api_key' => Crypt::encryptString(self::KEY)]);
        ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false)->setLogErrors(false)->scrape();

        $this->setFirecrawl(['enabled' => true]);
        ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false)->setLogErrors(false)->scrape();

        $this->assertSame(0, $this->firecrawlCalls());
    }

    public function test_env_key_is_used_instead_of_settings_key(): void
    {
        config(['services.firecrawl.key' => 'fc-from-env']);
        $this->fakeShopAndFirecrawl(Http::response('Forbidden', 403));

        ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false)->scrape();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.firecrawl.dev')
            && $r->hasHeader('Authorization', 'Bearer fc-from-env'));
    }

    public function test_firecrawl_is_called_once_per_scrape_even_when_it_fails(): void
    {
        $this->fakeShopAndFirecrawl(Http::response('Forbidden', 403), ['success' => false, 'error' => 'blocked'], 500);

        ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false)->setMaxAttempts(3)->scrape();

        $this->assertSame(1, $this->firecrawlCalls());
    }

    public function test_daily_limit_stops_firecrawl(): void
    {
        $this->setFirecrawl(['enabled' => true, 'api_key' => Crypt::encryptString(self::KEY), 'daily_limit' => 1]);
        $this->fakeShopAndFirecrawl(Http::response('Forbidden', 403));

        ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false)->scrape();
        ScrapeUrl::new('https://shop.example.com/product/2')->setSendUiNotifications(false)->setLogErrors(false)->scrape();

        $this->assertSame(1, $this->firecrawlCalls());
        $this->assertSame(1, FirecrawlService::new()->usedToday());
    }

    public function test_a_fetched_page_is_cached_and_not_paid_for_twice(): void
    {
        $this->fakeShopAndFirecrawl(Http::response('Forbidden', 403));

        ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false)->scrape();
        ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false)->scrape(['use_cache' => false]);

        $this->assertSame(1, $this->firecrawlCalls());
    }

    public function test_key_is_never_written_to_the_logs(): void
    {
        LogMessage::query()->delete();
        // An error body that echoes the key back must not reach the logs.
        $this->fakeShopAndFirecrawl(Http::response('Forbidden', 403), ['success' => false, 'error' => 'Invalid token Bearer '.self::KEY], 401);

        ScrapeUrl::new(self::SHOP_URL)->setSendUiNotifications(false)->scrape();

        $logs = LogMessage::query()->get()->map(fn ($l) => $l->message.json_encode($l->context))->join("\n");
        $this->assertStringContainsString('Firecrawl could not fetch the page', $logs);
        $this->assertStringNotContainsString(self::KEY, $logs);
    }

    public function test_key_is_not_sent_over_plain_http(): void
    {
        config(['services.firecrawl.url' => 'http://api.firecrawl.dev']);
        Http::fake(['*' => Http::response('Forbidden', 403)]);

        $this->assertNull(FirecrawlService::new()->fetchHtml(self::SHOP_URL));
        Http::assertNothingSent();
    }

    public function test_private_addresses_are_never_sent_to_firecrawl(): void
    {
        Http::fake();

        foreach (['http://localhost/x', 'http://192.168.1.10/x', 'http://127.0.0.1:8080/x', 'file:///etc/passwd'] as $url) {
            $this->assertNull(FirecrawlService::new()->fetchHtml($url));
        }

        Http::assertNothingSent();
    }

    public function test_blocked_search_page_uses_firecrawl_for_an_opted_in_store(): void
    {
        $results = '<div class="item"><h2>Kids Crocs 33</h2><a href="https://shop.example.com/p/1">x</a><span class="p">999</span></div>'.str_repeat('<nav>menu</nav>', 200);

        Http::fake([
            'api.firecrawl.dev/*' => Http::response(['success' => true, 'data' => ['rawHtml' => $results, 'metadata' => ['statusCode' => 200]]]),
            'shop.example.com/*' => Http::response('Forbidden', 403),
        ]);

        $source = ProductSource::factory()->create([
            'store_id' => $this->store->getKey(),
            'search_url' => 'https://shop.example.com/search?q=:search_term',
            'settings' => ['scraper_service' => 'http'],
            'extraction_strategy' => [
                'list_container' => ['type' => 'selector', 'value' => '!.item'],
                'product_title' => ['type' => 'selector', 'value' => 'h2'],
                'product_url' => ['type' => 'selector', 'value' => 'a|href'],
                'product_price' => ['type' => 'selector', 'value' => '.p'],
            ],
        ]);

        $found = ProductSourceSearchService::new($source)->search('kids crocs');

        $this->assertCount(1, $found);
        $this->assertSame('Kids Crocs 33', $found->first()['title']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.firecrawl.dev')
            && $r['url'] === 'https://shop.example.com/search?q=kids+crocs');
    }

    public function test_search_with_no_results_does_not_use_firecrawl(): void
    {
        Http::fake([
            'api.firecrawl.dev/*' => Http::response(['success' => true, 'data' => ['rawHtml' => '<p>x</p>']]),
            'shop.example.com/*' => Http::response('<html><body><p>No results for your search</p>'.str_repeat('<nav>menu</nav>', 200).'</body></html>'),
        ]);

        $source = ProductSource::factory()->create([
            'store_id' => $this->store->getKey(),
            'search_url' => 'https://shop.example.com/search?q=:search_term',
            'settings' => ['scraper_service' => 'http'],
            'extraction_strategy' => [
                'list_container' => ['type' => 'selector', 'value' => '!.item'],
                'product_title' => ['type' => 'selector', 'value' => 'h2'],
                'product_url' => ['type' => 'selector', 'value' => 'a|href'],
            ],
        ]);

        $this->assertCount(0, ProductSourceSearchService::new($source)->search('nothing'));
        $this->assertSame(0, $this->firecrawlCalls());
    }

    public function test_looks_blocked(): void
    {
        $this->assertTrue(FirecrawlService::looksBlocked(''));
        $this->assertTrue(FirecrawlService::looksBlocked('<html>ok</html>', [['message' => '403']]));
        $this->assertTrue(FirecrawlService::looksBlocked('<title>Attention Required! | Cloudflare</title>'));
        $this->assertTrue(FirecrawlService::looksBlocked('<script src="/cdn-cgi/challenge-platform/h/b/orchestrate"></script>'));
        $this->assertTrue(FirecrawlService::looksBlocked('<title>Robot Check</title>'));
        $this->assertTrue(FirecrawlService::looksBlocked('Forbidden'));
        $this->assertFalse(FirecrawlService::looksBlocked('{"products":[]}'));
        $this->assertFalse(FirecrawlService::looksBlocked('<html><title>Kids Crocs | Jumia</title>'.str_repeat('<p>x</p>', 300).'</html>'));
    }
}
