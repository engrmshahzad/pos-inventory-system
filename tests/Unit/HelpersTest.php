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

    public function test_product_quantity_format_keeps_whole_numbers(): void
    {
        $product = new \App\Models\Product;

        $this->assertSame('120', $product->formatQty(120));
        $this->assertSame('10', $product->formatQty('10.000'));
        $this->assertSame('1,500', $product->formatQty(1500));
        $this->assertSame('1.5', $product->formatQty('1.500'));
        $this->assertSame('0.25', $product->formatQty(0.25));
        $this->assertSame('-4', $product->formatQty(-4));
        $this->assertSame('0', $product->formatQty(null));
    }
}
