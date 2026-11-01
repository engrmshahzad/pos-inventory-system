<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('ntn', 30)->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0); // current payable, kept in sync by services
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('purchase_no', 30)->unique();
            $table->string('reference_no', 60)->nullable(); // supplier's own invoice number
            $table->date('purchase_date');
            $table->string('status', 15)->default('received')->index(); // received, ordered
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->string('payment_status', 10)->default('due')->index();
            $table->string('note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'purchase_date']);
            $table->index(['supplier_id', 'purchase_date']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->decimal('new_sale_price', 12, 2)->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('batch_no', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('return_no', 30)->unique();
            $table->decimal('total', 12, 2);
            $table->string('refund_method', 20); // cash, card, jazzcash, easypaisa, bank, adjust
            $table->string('reason')->nullable();
            $table->timestamp('returned_at');
            $table->timestamps();

            $table->index(['branch_id', 'returned_at']);
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2); // refund per unit (after discounts, incl. tax)
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('purchase_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('return_no', 30)->unique();
            $table->decimal('total', 12, 2);
            $table->string('settle_method', 20); // adjust (reduce payable) or a refund method (cash, bank...)
            $table->string('reason')->nullable();
            $table->timestamp('returned_at');
            $table->timestamps();

            $table->index(['branch_id', 'returned_at']);
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_no', 30)->nullable()->unique()->after('id');
            $table->foreignId('supplier_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->morphs('allocatable'); // Sale or Purchase
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('payment_no');
        });
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};
