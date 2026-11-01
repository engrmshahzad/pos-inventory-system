<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Setting;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private Branch $branch;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockService::class);
        $this->branch = Branch::factory()->create();
        $this->product = Product::factory()->create(['purchase_price' => 100]);
        $this->stock->ensureRowsForProduct($this->product);
    }

    public function test_increase_updates_quantity_and_writes_movement(): void
    {
        $movement = $this->stock->increase($this->branch->id, $this->product->id, 10, 'purchase', null, 'HYD-P-000001');

        $this->assertSame(10.0, $this->stock->quantity($this->branch->id, $this->product->id));
        $this->assertSame('purchase', $movement->type);
        $this->assertEquals(10, $movement->quantity);
        $this->assertEquals(10, $movement->balance_after);
        $this->assertEquals(100, $movement->unit_cost);
        $this->assertSame('HYD-P-000001', $movement->reference_no);
    }

    public function test_decrease_writes_negative_movement(): void
    {
        $this->stock->increase($this->branch->id, $this->product->id, 10, 'purchase');
        $movement = $this->stock->decrease($this->branch->id, $this->product->id, 3.5, 'sale');

        $this->assertSame(6.5, $this->stock->quantity($this->branch->id, $this->product->id));
        $this->assertEquals(-3.5, $movement->quantity);
        $this->assertEquals(6.5, $movement->balance_after);
    }

    public function test_decrease_beyond_stock_throws_and_changes_nothing(): void
    {
        $this->stock->increase($this->branch->id, $this->product->id, 2, 'purchase');

        try {
            $this->stock->decrease($this->branch->id, $this->product->id, 5, 'sale');
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame(2.0, $e->available);
            $this->assertSame(5.0, $e->requested);
            $this->assertStringContainsString($this->product->name, $e->getMessage());
        }

        $this->assertSame(2.0, $this->stock->quantity($this->branch->id, $this->product->id));
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_negative_stock_allowed_by_setting(): void
    {
        Setting::set('allow_negative_stock', '1', 'pos');

        $this->stock->decrease($this->branch->id, $this->product->id, 4, 'sale');

        $this->assertSame(-4.0, $this->stock->quantity($this->branch->id, $this->product->id));
    }

    public function test_rejects_zero_quantity_and_wrong_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->stock->increase($this->branch->id, $this->product->id, 0, 'purchase');
    }

    public function test_rejects_out_type_on_increase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->stock->increase($this->branch->id, $this->product->id, 1, 'sale');
    }

    public function test_missing_stock_row_is_created(): void
    {
        $other = Product::factory()->create();

        $this->stock->increase($this->branch->id, $other->id, 7, 'adjustment');

        $this->assertSame(7.0, $this->stock->quantity($this->branch->id, $other->id));
    }

    public function test_stock_is_kept_per_branch(): void
    {
        $second = Branch::factory()->create();
        $this->stock->ensureRowsForBranch($second);

        $this->stock->increase($this->branch->id, $this->product->id, 10, 'purchase');
        $this->stock->increase($second->id, $this->product->id, 3, 'purchase');
        $this->stock->decrease($second->id, $this->product->id, 1, 'transfer_out');

        $this->assertSame(10.0, $this->stock->quantity($this->branch->id, $this->product->id));
        $this->assertSame(2.0, $this->stock->quantity($second->id, $this->product->id));
    }
}
