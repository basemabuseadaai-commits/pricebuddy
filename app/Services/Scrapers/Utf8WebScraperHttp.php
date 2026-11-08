<?php

namespace App\Services\Scrapers;

use App\Services\Helpers\HtmlEncodingHelper;
use Exception;
use Illuminate\Support\Facades\Cache;
use Jez500\WebScraperForLaravel\WebScraperHttp;

/**
 * The package scraper, with the response body converted to UTF-8 before it is
 * cached or parsed (#203). AppServiceProvider binds it in place of WebScraperHttp.
 */
class Utf8WebScraperHttp extends WebScraperHttp
{
    /**
     * Shops that only allow identified bots get an honest User-Agent with a
     * contact (config/scraper_bot.php) instead of a browser one.
     */
    public function buildHeaders(): array
    {
        $headers = parent::buildHeaders();
        $contact = config('scraper_bot.contact');

        if (filled($contact) && self::isIdentifiedHost((string) parse_url((string) $this->url, PHP_URL_HOST))) {
            $name = config('scraper_bot.name', 'BSBS-PriceChecker/1.0');
            $headers['User-Agent'] = $name.' (personal price tracker; +mailto:'.$contact.')';
            $headers['From'] = $contact;
        }

        return $headers;
    }

    public static function isIdentifiedHost(string $host): bool
    {
        $host = strtolower($host);

        foreach ((array) config('scraper_bot.hosts', []) as $domain) {
            $domain = strtolower(trim((string) $domain));
            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain))) {
                return true;
            }
        }

        return false;
    }

    public function get(): self
    {
        $request = function () {
            try {
                $response = $this->getRequest()->get($this->url);

                return HtmlEncodingHelper::toUtf8($response->body(), $response->header('Content-Type'));
            } catch (Exception $e) {
                $this->errors[] = [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                ];
                logger()->error($e->getMessage());
            }

            return '';
        };

        $this->body = $this->useCache === true
            ? Cache::remember(
                $this->getCacheKey($this->url),
                now()->addMinutes($this->cacheMinsTtl),
                fn () => $request()
            )
            : $request();

        return $this;
    }
}
