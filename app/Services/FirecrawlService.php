<?php

namespace App\Services;

use App\Enums\IntegratedServices;
use App\Models\Store;
use App\Services\Ai\SecretRedactor;
use App\Services\Helpers\IntegrationHelper;
use App\Settings\AppSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches a page through Firecrawl (firecrawl.dev), a paid scraping API, when a
 * store's own scrape is blocked. Only for stores (or search sources) that opt in
 * (settings.firecrawl_fallback; callers check isEnabledFor()),
 * and only when Settings > Scraping > Firecrawl is enabled and a key is set.
 *
 * Keeping the key safe:
 *  - The key comes from FIRECRAWL_API_KEY in .env, or from Settings, where it is stored
 *    encrypted with APP_KEY (like the AI provider keys) and never sent back to the browser.
 *  - It is only ever sent to the Firecrawl API URL from config (https only), never to a
 *    store or to a URL a user can type in the app.
 *  - It is removed from anything logged, and errors never include request headers.
 *  - Store cookies are never forwarded to Firecrawl.
 *  - A daily page limit stops a runaway loop (or a stolen app session) from spending credits.
 */
class FirecrawlService
{
    public const string SETTINGS_KEY = 'firecrawl';

    public const int DEFAULT_DAILY_LIMIT = 50;

    public const array PROXY_OPTIONS = ['basic', 'auto', 'enhanced'];

    public const string DEFAULT_PROXY = 'auto';

    /**
     * HTML pages shorter than this are treated as error pages.
     */
    public const int MIN_PAGE_BYTES = 1500;

    /**
     * Signs that a page is a bot wall rather than the shop's real page.
     */
    protected const string BLOCKED_BODY_PATTERN = '/<title>\s*(?:Just a moment\.\.\.|Attention Required!|Access Denied|Robot Check|Are you a robot\?|Pardon Our Interruption)'
        .'|cf-browser-verification|cf_chl_opt|\/cdn-cgi\/challenge-platform\/'
        .'|Type the characters you see in this image'
        .'|captcha-delivery\.com|px-captcha|_Incapsula_Resource/i';

    public static function new(): self
    {
        return resolve(static::class);
    }

    public static function settings(): array
    {
        return (array) data_get(IntegrationHelper::getSettings(), IntegratedServices::Firecrawl->value, []);
    }

    public static function hasStoredKey(): bool
    {
        return filled(data_get(self::settings(), 'api_key'));
    }

    /**
     * Firecrawl is switched on and has a usable key.
     */
    public function isEnabled(): bool
    {
        return (bool) data_get(self::settings(), 'enabled', false) && filled($this->apiKey());
    }

    /**
     * Firecrawl may be used for this store.
     */
    public function isEnabledFor(?Store $store): bool
    {
        return $store !== null && $store->firecrawl_fallback && $this->isEnabled();
    }

    /**
     * The key in .env wins over the one saved in Settings.
     */
    protected function apiKey(): ?string
    {
        $envKey = config('services.firecrawl.key');

        if (filled($envKey)) {
            return (string) $envKey;
        }

        $stored = data_get(self::settings(), 'api_key');

        if (blank($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $stored);
        } catch (DecryptException) {
            Log::warning('Firecrawl API key could not be decrypted; check APP_KEY and re-enter the key in Settings.');

            return null;
        }
    }

    public function dailyLimit(): int
    {
        $limit = (int) data_get(self::settings(), 'daily_limit', self::DEFAULT_DAILY_LIMIT);

        return $limit > 0 ? $limit : self::DEFAULT_DAILY_LIMIT;
    }

    public function usedToday(): int
    {
        return (int) Cache::get($this->counterKey(), 0);
    }

    protected function counterKey(): string
    {
        return 'firecrawl:pages:'.now()->format('Y-m-d');
    }

    /**
     * Whether a fetched page looks blocked: request errors, an empty body, or a bot wall.
     */
    public static function looksBlocked(?string $body, array $errors = []): bool
    {
        if (! empty($errors) || blank($body)) {
            return true;
        }

        $body = trim((string) $body);

        // A shop's real page (even "no results") is tens of KB; an error page such as
        // "Forbidden" or "Access Denied" is tiny. JSON answers can be small, so skip those.
        if (strlen($body) < self::MIN_PAGE_BYTES && ! str_starts_with($body, '{') && ! str_starts_with($body, '[')) {
            return true;
        }

        return preg_match(self::BLOCKED_BODY_PATTERN, $body) === 1;
    }

    /**
     * The page's raw HTML via Firecrawl, or null when it can't or shouldn't be fetched.
     */
    public function fetchHtml(string $url): ?string
    {
        if (! $this->isEnabled() || ! self::isPublicHttpUrl($url)) {
            return null;
        }

        $ttl = max(1, AppSettings::new()->scrape_cache_ttl);
        $cacheKey = 'firecrawl:html:'.sha1($url);

        if (is_string($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        if ($this->usedToday() >= $this->dailyLimit()) {
            $this->log('warning', 'Firecrawl daily page limit reached, skipping', ['url' => $url, 'limit' => $this->dailyLimit()]);

            return null;
        }

        $baseUrl = rtrim((string) config('services.firecrawl.url', 'https://api.firecrawl.dev'), '/');

        if (! str_starts_with($baseUrl, 'https://')) {
            $this->log('error', 'Firecrawl URL must use https; not sending the key', []);

            return null;
        }

        $key = (string) $this->apiKey();
        $this->countPage();

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout((int) config('services.firecrawl.timeout', 90))
                ->post($baseUrl.'/v2/scrape', [
                    'url' => $url,
                    'formats' => ['rawHtml'],
                    'onlyMainContent' => false,
                    'proxy' => $this->proxy(),
                    'location' => ['country' => 'EG', 'languages' => ['en-US', 'ar-EG']],
                    'maxAge' => $ttl * 60 * 1000,
                    'timeout' => 60000,
                ]);
        } catch (Throwable $e) {
            $this->log('error', 'Firecrawl request failed', ['url' => $url, 'error' => SecretRedactor::redact($e->getMessage(), $key)]);

            return null;
        }

        if (! $response->successful() || ! $response->json('success')) {
            $this->log('error', 'Firecrawl could not fetch the page', [
                'url' => $url,
                'status' => $response->status(),
                'error' => SecretRedactor::redact(mb_substr((string) ($response->json('error') ?? $response->body()), 0, 300), $key),
            ]);

            return null;
        }

        $html = $response->json('data.rawHtml');
        $pageStatus = (int) $response->json('data.metadata.statusCode', 200);

        if (! is_string($html) || blank($html) || $pageStatus >= 400 || self::looksBlocked($html)) {
            $this->log('warning', 'Firecrawl reached the page but it was still blocked or empty', ['url' => $url, 'page_status' => $pageStatus]);

            return null;
        }

        Cache::put($cacheKey, $html, now()->addMinutes($ttl));
        $this->log('info', 'Fetched page through Firecrawl', ['url' => $url, 'used_today' => $this->usedToday()]);

        return $html;
    }

    protected function proxy(): string
    {
        $proxy = (string) data_get(self::settings(), 'proxy', self::DEFAULT_PROXY);

        return in_array($proxy, self::PROXY_OPTIONS, true) ? $proxy : self::DEFAULT_PROXY;
    }

    protected function countPage(): void
    {
        $key = $this->counterKey();
        Cache::add($key, 0, now()->endOfDay()->addHour());
        Cache::increment($key);
    }

    /**
     * Only public http(s) pages; never localhost or the home network.
     */
    protected static function isPublicHttpUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || $host === 'localhost') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return str_contains($host, '.');
    }

    protected function log(string $level, string $message, array $context): void
    {
        try {
            Log::channel('db')->{$level}($message, $context);
        } catch (Throwable) {
            Log::{$level}($message, $context);
        }
    }
}
