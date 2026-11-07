<?php

namespace Tests\Unit\Services;

use App\Services\ProductSourceSearchService;
use Tests\TestCase;

class ProductSourceBrowseTest extends TestCase
{
    public function test_items_are_read_from_embedded_json()
    {
        $json = json_encode(['props' => ['pageProps' => ['initialState' => ['itemsData' => ['items' => [
            ['title' => 'Reefy Full Cream Fresh Milk 850ml', 'price' => 58.3, 'slug' => 'reefy-milk', 'sku' => '908522'],
            ['title' => 'No slug', 'price' => 10, 'sku' => '1'],
        ]]]]]]);
        $html = '<html><script id="__NEXT_DATA__" type="application/json">'.$json.'</script></html>';

        $items = ProductSourceSearchService::itemsFromEmbeddedJson($html, [
            'items_path' => 'props.pageProps.initialState.itemsData.items',
            'url_template' => 'https://t.eg/product/{slug}/s/{sku}?aid=1',
        ]);

        $this->assertSame([[
            'title' => 'Reefy Full Cream Fresh Milk 850ml',
            'url' => 'https://t.eg/product/reefy-milk/s/908522?aid=1',
            'price' => 58.3,
        ]], $items);
    }
}
