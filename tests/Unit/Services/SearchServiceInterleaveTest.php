<?php

namespace Tests\Unit\Services;

use App\Services\SearchService;
use PHPUnit\Framework\TestCase;

class SearchServiceInterleaveTest extends TestCase
{
    public function test_interleave_takes_one_from_each_source_in_turn()
    {
        $this->assertSame(
            ['a1', 'b1', 'c1', 'a2', 'c2', 'a3'],
            SearchService::interleave([['a1', 'a2', 'a3'], ['b1'], ['c1', 'c2']])
        );
    }

    public function test_interleave_handles_no_sources()
    {
        $this->assertSame([], SearchService::interleave([]));
        $this->assertSame([], SearchService::interleave([[], []]));
    }
}
