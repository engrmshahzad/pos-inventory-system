<?php

namespace Tests\Unit;

use Tests\TestCase;

class HelpersTest extends TestCase
{
    protected bool $seedRoles = false;

    public function test_number_formats(): void
    {
        $this->assertSame('1,250,000', formatNumber(1250000, 0, 'international'));
        $this->assertSame('12,50,000', formatNumber(1250000, 0, 'indian'));
        $this->assertSame('1,25,000.50', formatNumber(125000.5, 2, 'indian'));
        $this->assertSame('999', formatNumber(999, 0, 'indian'));
        $this->assertSame('-1,25,000', formatNumber(-125000, 0, 'indian'));
    }
}
