<?php

namespace Tests\Unit\Services;

use App\Services\SavedSearchRunner;
use Tests\TestCase;

class SavedSearchRunnerTest extends TestCase
{
    protected function results()
    {
        return collect([
            ['title' => 'Korkmaz Granite Pan 28cm', 'url' => 'https://a/1', 'price' => 1500.0],
            ['title' => 'Korkmaz Ceramic Pot', 'url' => 'https://a/2', 'price' => 900.0],
            ['title' => 'Korkmaz Steel Set', 'url' => 'https://a/3', 'price' => 12000.0],
            ['title' => 'Korkmaz Granite Lid', 'url' => 'https://a/4', 'price' => 200.0],
            ['title' => 'Granite pan, no price', 'url' => 'https://a/5', 'price' => null],
        ]);
    }

    public function test_required_words_accept_alternatives()
    {
        $kept = SavedSearchRunner::filterResults($this->results(), ['korkmaz', 'granite|ceramic'], [], null, null);

        $this->assertSame(['https://a/1', 'https://a/2', 'https://a/4'], $kept->pluck('url')->all());
    }

    public function test_required_words_accept_comma_alternatives()
    {
        $kept = SavedSearchRunner::filterResults($this->results(), ['korkmaz', 'granite, ceramic'], ['steel, lid'], null, null);

        $this->assertSame(['https://a/1', 'https://a/2'], $kept->pluck('url')->all());
    }

    public function test_excluded_words_and_price_limits()
    {
        $kept = SavedSearchRunner::filterResults($this->results(), ['granite'], ['lid'], 1000, 2000);

        // The result without a price is kept: its price is checked on the product page.
        $this->assertSame(['https://a/1', 'https://a/5'], $kept->pluck('url')->all());
    }

    public function test_cheapest_puts_unpriced_last_and_limits_count()
    {
        $picked = SavedSearchRunner::cheapest($this->results(), 3);

        $this->assertSame(['https://a/4', 'https://a/2', 'https://a/1'], $picked->pluck('url')->all());
        $this->assertSame('https://a/5', SavedSearchRunner::cheapest($this->results(), 10)->last()['url']);
    }

    public function test_amazon_links_keep_only_the_product_part()
    {
        $this->assertSame(
            'https://www.amazon.eg/-/en/Crocs-Bayaband-Clog/dp/B077RYB6R8',
            SavedSearchRunner::canonicalUrl('https://www.amazon.eg/-/en/Crocs-Bayaband-Clog/dp/B077RYB6R8/ref=sr_1_3?dib=abc&keywords=x')
        );
        $this->assertSame(
            'https://www.noon.com/egypt-en/x/p/?o=abc',
            SavedSearchRunner::canonicalUrl('https://www.noon.com/egypt-en/x/p/?o=abc')
        );
    }

    public function test_tracking_parameters_are_dropped()
    {
        $this->assertSame(
            'https://www.defacto.com.eg/jeans-123',
            SavedSearchRunner::canonicalUrl('https://www.defacto.com.eg/jeans-123?waw_keyword=jeans')
        );
        $this->assertSame(
            'https://a.eg/p?id=2',
            SavedSearchRunner::canonicalUrl('https://a.eg/p?id=2&utm_source=x')
        );
    }

    public function test_words_match_whole_words_and_plurals()
    {
        $this->assertFalse(SavedSearchRunner::hasWord('Braided rug', 'bra'));
        $this->assertFalse(SavedSearchRunner::hasWord('Stop sign', 'top'));
        $this->assertTrue(SavedSearchRunner::hasWord('Dri-FIT Pacer Top', 'top'));
        $this->assertTrue(SavedSearchRunner::hasWord('Nike sneakers', 'sneaker'));
    }
}
