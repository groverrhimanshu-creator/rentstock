<?php

namespace Tests\Unit;

use App\Support\Pager;
use PHPUnit\Framework\TestCase;

class PagerTest extends TestCase
{
    public function test_default_is_20_and_only_allowed_sizes_pass(): void
    {
        $this->assertSame(20, Pager::size(null));
        $this->assertSame(20, Pager::size(7));
        $this->assertSame(50, Pager::size('50'));
        $this->assertSame(100, Pager::size(100));
    }
}
