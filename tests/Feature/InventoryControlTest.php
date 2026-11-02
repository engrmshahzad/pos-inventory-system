<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryControlTest extends TestCase
{
    use RefreshDatabase;

    private Branch $hyd;
    private Branch $khi;
    private User $hydManager;
    private User $khiManager;
    private Product $tea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hyd = Branch::factory()->create(['code' => 'HYD', 'name' => 'Hyderabad']);
        $this->khi = Branch::factory()->create(['code' => 'KHI', 'name' => 'Karachi']);
        $this->hydManager = $this->makeUser('manager', $this->hyd);
        $this->khiManager = $this->makeUser('manager', $this->khi);
        $pcs = Unit::factory()->create(['short_name' => 'pcs']);
        $this->tea = app(ProductService::class)->create(['name' => 'Tapal 950g', 'unit_id' => $pcs->id, 'purchase_price' => 1500, 'sale_price' => 1650, 'min_stock' => 5, 'is_active' => true],
            null, [$this->hyd->id => 20, $this->khi->id => 2]);
        Setting::set('adjustment_approval_threshold', '5000', 'alerts');
    }

    private function qty(Branch $branch): float
    {
        return (float) $this->tea->stocks()->where('branch_id', $branch->id)->value('quantity');
    }

    private function transfer(float $qty = 10, string $action = 'send', ?User $user = null, array $extra = [])
    {
        return $this->actingAs($user ?? $this->hydManager)->post('/transfers', $extra + [
            'to_branch_id' => $this->khi->id, 'transfer_date' => today()->toDateString(), 'action' => $action,
            'items' => [['product_id' => $this->tea->id, 'quantity' => $qty]],
        ]);
    }

    public function test_send_and_full_receive_move_stock_and_notify(): void
    {
        $this->transfer()->assertSessionHasNoErrors();
        $t = StockTransfer::first();

        $this->assertSame('TR-000001', $t->transfer_no);
        $this->assertSame('sent', $t->status);
        $this->assertSame(10.0, $this->qty($this->hyd));
        $this->assertSame(2.0, $this->qty($this->khi)); // not yet received
        $this->assertSame(1, $this->khiManager->unreadNotifications()->count());

        $this->actingAs($this->khiManager)->post("/transfers/{$t->id}/receive", [])->assertSessionHasNoErrors();
        $this->assertSame('received', $t->fresh()->status);
        $this->assertSame(12.0, $this->qty($this->khi));
        $this->assertDatabaseHas('stock_movements', ['type' => 'transfer_out', 'branch_id' => $this->hyd->id, 'quantity' => -10]);
        $this->assertDatabaseHas('stock_movements', ['type' => 'transfer_in', 'branch_id' => $this->khi->id, 'quantity' => 10]);
    }

    public function test_partial_receive_records_shortage(): void
    {
        $this->transfer();
        $t = StockTransfer::with('items')->first();

        $this->actingAs($this->khiManager)->post("/transfers/{$t->id}/receive", ['received' => [$t->items[0]->id => 7], 'receive_note' => '3 broken'])
            ->assertSessionHasNoErrors();

        $this->assertSame('partially_received', $t->fresh()->status);
        $this->assertEquals(3, $t->items[0]->fresh()->shortage);
        $this->assertSame(9.0, $this->qty($this->khi));
        $this->assertSame(10.0, $this->qty($this->hyd));
    }

    public function test_cannot_send_more_than_available_or_to_same_branch(): void
    {
        $this->transfer(25)->assertSessionHasErrors('items');
        $this->transfer(5, 'send', null, ['to_branch_id' => $this->hyd->id])->assertSessionHasErrors('to_branch_id');
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_only_receiving_branch_can_receive(): void
    {
        $this->transfer();
        $t = StockTransfer::first();

        $this->actingAs($this->hydManager)->post("/transfers/{$t->id}/receive", [])->assertSessionHasErrors('action');
        $this->assertSame('sent', $t->fresh()->status);

        $sukManager = $this->makeUser('manager', Branch::factory()->create());
        $this->actingAs($sukManager)->get("/transfers/{$t->id}")->assertForbidden();
    }

    public function test_manager_cannot_cancel_sent_but_owner_can_and_stock_returns(): void
    {
        $this->transfer();
        $t = StockTransfer::first();

        $this->actingAs($this->hydManager)->post("/transfers/{$t->id}/cancel")->assertSessionHasErrors('action');

        $this->actingAs($this->owner())->post("/transfers/{$t->id}/cancel", ['cancel_reason' => 'Wrong branch'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $t->fresh()->status);
        $this->assertSame(20.0, $this->qty($this->hyd));
        $this->assertSame(2.0, $this->qty($this->khi));
    }

    public function test_owner_request_draft_then_branch_sends(): void
    {
        $owner = $this->owner();
        $this->transfer(4, 'draft', $owner, ['from_branch_id' => $this->hyd->id])->assertSessionHasNoErrors();
        $t = StockTransfer::first();

        $this->assertTrue($t->is_request);
        $this->assertSame(20.0, $this->qty($this->hyd));
        $this->assertSame(1, $this->hydManager->unreadNotifications()->count());

        $this->actingAs($this->khiManager)->post("/transfers/{$t->id}/send")->assertSessionHasErrors('action');
        $this->actingAs($this->hydManager)->post("/transfers/{$t->id}/send")->assertSessionHasNoErrors();
        $this->assertSame(16.0, $this->qty($this->hyd));
    }

    public function test_suggestions_find_low_branch_with_donor(): void
    {
        $this->actingAs($this->owner())->get('/transfers/suggestions')->assertOk()->assertSee('Tapal 950g');
        $this->actingAs($this->hydManager)->get('/transfers/suggestions')->assertForbidden();
    }

    public function test_suggestions_never_promise_the_same_spare_stock_twice(): void
    {
        $suk = Branch::factory()->create(['code' => 'SUK']);
        // min 5 → donor keeps 10; HYD has 20 so 10 spare. KHI (2) and SUK (0) both need stock.
        $this->tea->stocks()->where('branch_id', $suk->id)->update(['quantity' => 0]);
        app(\App\Services\StockService::class)->ensureRowsForBranch($suk);

        $suggestions = app(\App\Services\TransferService::class)->suggestions()->where('product_id', $this->tea->id);

        $this->assertLessThanOrEqual(10, $suggestions->sum('suggested'));
        $this->assertSame([$this->hyd->id], $suggestions->pluck('from_branch_id')->unique()->values()->all());
    }

    public function test_small_manager_adjustment_applies_immediately(): void
    {
        $this->actingAs($this->hydManager)->post('/adjustments', [
            'adjustment_date' => today()->toDateString(), 'reason' => 'damaged',
            'items' => [['product_id' => $this->tea->id, 'quantity' => -2]],
        ])->assertSessionHasNoErrors();

        $adj = StockAdjustment::first();
        $this->assertSame('approved', $adj->status);
        $this->assertEquals(-3000, $adj->total_value);
        $this->assertSame(18.0, $this->qty($this->hyd));
    }

    public function test_large_manager_adjustment_waits_for_owner(): void
    {
        $owner = $this->owner();
        $this->actingAs($this->hydManager)->post('/adjustments', [
            'adjustment_date' => today()->toDateString(), 'reason' => 'lost',
            'items' => [['product_id' => $this->tea->id, 'quantity' => -4]],
        ])->assertSessionHasNoErrors();

        $adj = StockAdjustment::first();
        $this->assertSame('pending', $adj->status);
        $this->assertSame(20.0, $this->qty($this->hyd));
        $this->assertSame(1, $owner->unreadNotifications()->count());

        $this->actingAs($this->hydManager)->post("/adjustments/{$adj->id}/approve")->assertForbidden();
        $this->actingAs($owner)->post("/adjustments/{$adj->id}/approve")->assertSessionHasNoErrors();
        $this->assertSame('approved', $adj->fresh()->status);
        $this->assertSame(16.0, $this->qty($this->hyd));
        $this->assertSame(1, $this->hydManager->unreadNotifications()->count());
    }

    public function test_rejected_adjustment_never_touches_stock(): void
    {
        $this->actingAs($this->hydManager)->post('/adjustments', [
            'adjustment_date' => today()->toDateString(), 'reason' => 'lost', 'items' => [['product_id' => $this->tea->id, 'quantity' => -5]],
        ]);
        $adj = StockAdjustment::first();

        $this->actingAs($this->owner())->post("/adjustments/{$adj->id}/reject", ['rejection_reason' => 'Recount first'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $adj->fresh()->status);
        $this->assertSame(20.0, $this->qty($this->hyd));
    }

    public function test_stock_count_applies_one_adjustment_for_differences(): void
    {
        $owner = $this->owner();
        $coffee = app(ProductService::class)->create(['name' => 'Nescafe', 'unit_id' => $this->tea->unit_id, 'purchase_price' => 800, 'sale_price' => 950, 'is_active' => true], null, [$this->hyd->id => 5]);

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->hyd->id])->post('/stock-counts', [])->assertSessionHasNoErrors();
        $count = StockCount::with('items')->first();
        $this->assertCount(2, $count->items);

        $teaItem = $count->items->firstWhere('product_id', $this->tea->id);
        $coffeeItem = $count->items->firstWhere('product_id', $coffee->id);

        // a second count at the same branch is blocked while this one is open
        $this->actingAs($owner)->withSession(['current_branch_id' => $this->hyd->id])->post('/stock-counts', [])->assertSessionHasErrors('action');

        $this->actingAs($owner)->put("/stock-counts/{$count->id}", ['counted' => [$teaItem->id => 18, $coffeeItem->id => 5], 'then' => 'apply'])
            ->assertSessionHasNoErrors();

        $count->refresh();
        $this->assertSame('applied', $count->status);
        $adj = $count->adjustment;
        $this->assertSame('stock_count', $adj->source);
        $this->assertCount(1, $adj->items); // coffee had no difference
        $this->assertEquals(-2, $adj->items[0]->quantity);
        $this->assertSame(18.0, $this->qty($this->hyd));
    }

    public function test_pages_render_and_bell_shows_notifications(): void
    {
        $owner = $this->owner();
        $this->transfer();
        $t = StockTransfer::first();
        $this->actingAs($this->hydManager)->post('/adjustments', ['adjustment_date' => today()->toDateString(), 'reason' => 'lost', 'items' => [['product_id' => $this->tea->id, 'quantity' => -4]]]);
        $adj = StockAdjustment::first();
        $this->actingAs($owner)->withSession(['current_branch_id' => $this->hyd->id])->post('/stock-counts', []);
        $count = StockCount::first();

        foreach (['/transfers', '/transfers?tab=pending', '/transfers/create', "/transfers/{$t->id}", '/transfers/suggestions', '/transfers/create?from=1&to=2&products['.$this->tea->id.']=3',
            '/adjustments', '/adjustments?status=pending', '/adjustments/create', "/adjustments/{$adj->id}", '/adjustments/create?reason=expired&products['.$this->tea->id.']=-2',
            '/stock-counts', "/stock-counts/{$count->id}", "/stock-counts/{$count->id}/sheet", '/expiry', '/expiry?days=90'] as $uri) {
            $this->actingAs($owner)->get($uri)->assertOk();
        }
        $this->actingAs($owner)->get("/transfers/{$t->id}/challan")->assertHeader('content-type', 'application/pdf');

        // Bell: KHI manager sees the incoming transfer; opening it marks it read
        $this->actingAs($this->khiManager)->get('/dashboard')->assertSee('Incoming transfer');
        $note = $this->khiManager->notifications()->first();
        $this->actingAs($this->khiManager)->get("/notifications/{$note->id}")->assertRedirect(route('transfers.show', $t));
        $this->assertNotNull($note->fresh()->read_at);
    }
}
