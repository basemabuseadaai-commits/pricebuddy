<?php

namespace Tests\Unit\Services\Scrapers;

use App\Services\Scrapers\Utf8WebScraperHttp;
use Tests\TestCase;

class IdentifiedBotHeadersTest extends TestCase
{
    public function test_identified_hosts_get_an_honest_user_agent()
    {
        config(['scraper_bot.contact' => 'bot@example.com', 'scraper_bot.hosts' => ['jumia.com.eg']]);

        $headers = (new Utf8WebScraperHttp)->from('https://www.jumia.com.eg/catalog/?q=pan')->buildHeaders();

        $this->assertStringContainsString('mailto:bot@example.com', $headers['User-Agent']);
        $this->assertSame('bot@example.com', $headers['From']);
    }

    public function test_other_hosts_and_missing_contact_are_unchanged()
    {
        config(['scraper_bot.contact' => 'bot@example.com', 'scraper_bot.hosts' => ['jumia.com.eg']]);
        $other = (new Utf8WebScraperHttp)->from('https://www.amazon.eg/s?k=pan')->buildHeaders();
        $this->assertArrayNotHasKey('From', $other);

        config(['scraper_bot.contact' => null]);
        $none = (new Utf8WebScraperHttp)->from('https://www.jumia.com.eg/')->buildHeaders();
        $this->assertArrayNotHasKey('From', $none);
    }

    public function test_subdomains_match_but_lookalikes_do_not()
    {
        config(['scraper_bot.hosts' => ['jumia.com.eg']]);

        $this->assertTrue(Utf8WebScraperHttp::isIdentifiedHost('www.jumia.com.eg'));
        $this->assertTrue(Utf8WebScraperHttp::isIdentifiedHost('jumia.com.eg'));
        $this->assertFalse(Utf8WebScraperHttp::isIdentifiedHost('notjumia.com.eg'));
    }
}
