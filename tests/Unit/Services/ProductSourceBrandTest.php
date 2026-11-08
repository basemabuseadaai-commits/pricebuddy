<?php

namespace Tests\Unit\Services;

use App\Services\ProductSourceSearchService;
use PHPUnit\Framework\TestCase;

class ProductSourceBrandTest extends TestCase
{
    public function test_brand_options_read_name_and_value_groups()
    {
        $html = '<li id="p_123/249377"><a><span class="a-size-base a-color-base">Neoflam</span></a></li>'
            .'<li id="p_123/397018"><a><span class="a-size-base a-color-base">Tefal</span></a></li>';
        $pattern = '~id="p_123/(?<value>\d+)".*?<span class="a-size-base[^"]*"[^>]*>(?<name>[^<]+)</span>~s';

        $this->assertSame(
            ['Neoflam' => '249377', 'Tefal' => '397018'],
            ProductSourceSearchService::brandOptions($html, $pattern)
        );
    }

    public function test_brand_options_use_the_name_as_value_without_a_value_group()
    {
        $html = '{\"code\":\"\'Neoflam\'\"},{\"code\":\"\'Lock|lock\'\"}';
        $pattern = '~\\\\?"code\\\\?":\\\\?"\'(?<name>[^\'"\\\\]+)\'~';

        $this->assertSame(
            ['Neoflam' => 'Neoflam', 'Lock|lock' => 'Lock|lock'],
            ProductSourceSearchService::brandOptions($html, $pattern)
        );
    }

    public function test_match_brand_ignores_case_and_prefers_exact_names()
    {
        $options = ['Neoflam Kitchen' => 'a', 'NEOFLAM' => 'b', 'Tefal' => 'c'];

        $this->assertSame('b', ProductSourceSearchService::matchBrand($options, 'neoflam'));
        $this->assertSame('a', ProductSourceSearchService::matchBrand(['Neoflam Kitchen' => 'a'], 'Neoflam'));
        $this->assertNull(ProductSourceSearchService::matchBrand($options, 'Korkmaz'));
    }
}
