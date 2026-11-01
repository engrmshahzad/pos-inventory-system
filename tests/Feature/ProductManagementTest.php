<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\BranchService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private Unit $pcs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pcs = Unit::factory()->create(['short_name' => 'pcs']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Tapal Danedar 950g',
            'unit_id' => $this->pcs->id,
            'purchase_price' => 1480,
            'sale_price' => 1650,
            'min_stock' => 10,
            'is_active' => '1',
        ];
    }

    public function test_creating_product_creates_stock_rows_for_every_branch_with_opening_stock(): void
    {
        [$hyd, $khi, $suk] = Branch::factory()->count(3)->create();

        $this->actingAs($this->owner())->post('/products', $this->payload([
            'opening_stock' => [$hyd->id => 12, $khi->id => '', $suk->id => 5],
        ]))->assertSessionHasNoErrors();

        $product = Product::firstWhere('name', 'Tapal Danedar 950g');
        $this->assertCount(3, $product->stocks);
        $this->assertEquals(12, $product->stocks->firstWhere('branch_id', $hyd->id)->quantity);
        $this->assertEquals(0, $product->stocks->firstWhere('branch_id', $khi->id)->quantity);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'branch_id' => $suk->id, 'type' => 'opening', 'quantity' => 5]);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_sku_and_valid_ean13_barcode_are_generated(): void
    {
        $this->actingAs($this->owner())->post('/products', $this->payload())->assertSessionHasNoErrors();

        $product = Product::first();
        $this->assertMatchesRegularExpression('/^PRD-\d{6}$/', $product->sku);
        $this->assertTrue(ProductService::isValidEan13($product->barcode));
    }

    public function test_sku_and_barcode_must_be_unique(): void
    {
        Product::factory()->create(['sku' => 'ABC-1', 'barcode' => '8964000000012']);

        $this->actingAs($this->owner())->post('/products', $this->payload(['sku' => 'abc-1', 'barcode' => '8964000000012']))
            ->assertSessionHasErrors(['sku', 'barcode']);
    }

    public function test_decimal_opening_stock_rejected_for_whole_number_unit(): void
    {
        $branch = Branch::factory()->create();

        $this->actingAs($this->owner())->post('/products', $this->payload(['opening_stock' => [$branch->id => 1.5]]))
            ->assertSessionHasErrors("opening_stock.{$branch->id}");
    }

    public function test_manager_opening_stock_only_goes_to_own_branch(): void
    {
        $manager = $this->makeUser('manager');
        $other = Branch::factory()->create();

        $this->actingAs($manager)->post('/products', $this->payload([
            'opening_stock' => [$manager->branch_id => 8, $other->id => 50],
        ]))->assertSessionHasNoErrors();

        $product = Product::first();
        $this->assertEquals(8, $product->stocks->firstWhere('branch_id', $manager->branch_id)->quantity);
        $this->assertEquals(0, $product->stocks->firstWhere('branch_id', $other->id)->quantity);
    }

    public function test_creating_branch_creates_stock_rows_for_every_product(): void
    {
        $service = app(ProductService::class);
        Branch::factory()->create();
        $service->create($this->payload());
        $service->create($this->payload(['name' => 'Second']));

        $branch = app(BranchService::class)->create(['name' => 'Larkana', 'code' => 'LRK', 'city' => 'Larkana', 'is_active' => true]);

        $this->assertSame(2, $branch->stocks()->count());
    }

    public function test_product_pages_render(): void
    {
        $owner = $this->owner();
        Branch::factory()->create();
        $product = app(ProductService::class)->create($this->payload(['image' => null]), UploadedFile::fake()->image('p.jpg'));

        foreach (['/products', '/products?stock=low', '/products/create', "/products/{$product->id}", "/products/{$product->id}?tab=history",
            "/products/{$product->id}/edit", '/products/import', '/products/barcodes?ids[]='.$product->id, '/categories', '/brands', '/units',
            '/stock', '/stock?stock=out', '/stock/movements'] as $uri) {
            $this->actingAs($owner)->get($uri)->assertOk();
        }

        $this->actingAs($owner)->get("/products/barcodes/print?size=50x25&items[0][id]={$product->id}&items[0][qty]=3")
            ->assertOk()->assertSee('3 labels')->assertSee('<svg', false);
        $this->actingAs($owner)->get('/products/search?q='.$product->barcode)->assertOk()->assertJsonPath('0.id', $product->id);
        $this->actingAs($owner)->get('/products/export')->assertOk()->assertDownload();
        $this->actingAs($owner)->get('/products/import/template')->assertOk()->assertDownload();
    }

    public function test_bulk_deactivate_and_delete(): void
    {
        $owner = $this->owner();
        $products = Product::factory()->count(3)->create();

        $this->actingAs($owner)->post('/products/bulk', ['action' => 'deactivate', 'ids' => $products->pluck('id')->all()]);
        $this->assertSame(0, Product::active()->count());

        $this->actingAs($owner)->post('/products/bulk', ['action' => 'delete', 'ids' => [$products[0]->id]]);
        $this->assertSoftDeleted($products[0]);
    }

    public function test_manager_sees_only_own_branch_stock(): void
    {
        $manager = $this->makeUser('manager');
        $other = Branch::factory()->create(['name' => 'Other Town Branch']);
        $product = app(ProductService::class)->create($this->payload(), null, [$manager->branch_id => 4, $other->id => 99]);

        $this->actingAs($manager)->get("/products/{$product->id}")
            ->assertOk()
            ->assertDontSee('Other Town Branch');

        $this->actingAs($manager)->get('/stock')->assertOk()->assertDontSee('99');
        $this->actingAs($manager)->get('/products/search?q=Tapal')->assertJsonPath('0.stock', 4);
    }

    public function test_cashier_cannot_access_catalogue_management(): void
    {
        $cashier = $this->makeUser('cashier');

        $this->actingAs($cashier)->get('/products')->assertForbidden();
        $this->actingAs($cashier)->get('/stock')->assertForbidden();
        $this->actingAs($cashier)->get('/categories')->assertForbidden();
        $this->actingAs($cashier)->post('/products', $this->payload())->assertForbidden();
        $this->actingAs($cashier)->get('/products/search?q=x')->assertOk(); // the POS needs product search
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->owner())->delete("/categories/{$category->id}")->assertSessionHasErrors('action');
        $this->assertNotSoftDeleted($category);
    }

    public function test_sub_category_cannot_be_nested_twice(): void
    {
        $owner = $this->owner();
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $this->actingAs($owner)->post('/categories', ['name' => 'Deep', 'parent_id' => $child->id, 'color' => '#4F46E5'])
            ->assertSessionHasErrors('parent_id');
    }
}
