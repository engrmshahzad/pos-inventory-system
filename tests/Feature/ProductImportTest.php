<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_saves_valid_rows_and_reports_failed_rows(): void
    {
        Unit::factory()->create(['short_name' => 'pcs']);
        $hyd = Branch::factory()->create(['code' => 'HYD']);

        $csv = implode("\n", [
            'name,sku,barcode,category,brand,unit,purchase_price,sale_price,wholesale_price,tax_percent,min_stock,track_expiry,description,stock_hyd',
            'Good Product,,,Grocery,Tapal,pcs,100,120,,0,5,no,,7',
            'Bad Unit,,,,,boxes,100,120,,0,5,no,,1',
            ',,,,,pcs,abc,120,,0,5,no,,1',
        ]);

        $response = $this->actingAs($this->owner())->post('/products/import', [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ]);

        $response->assertRedirect(route('products.import'));
        $report = session('import_report');
        $this->assertSame(1, $report['imported']);
        $this->assertCount(2, $report['failures']);
        $this->assertSame(3, $report['failures'][0]['row']);

        $product = Product::firstWhere('name', 'Good Product');
        $this->assertNotNull($product->category_id);
        $this->assertEquals(7, $product->stocks()->where('branch_id', $hyd->id)->value('quantity'));
    }
}
