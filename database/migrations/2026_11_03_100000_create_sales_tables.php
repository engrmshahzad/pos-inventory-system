<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->unique();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('cnic', 20)->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0); // 0 = no limit
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);      // current due (receivable), kept in sync by services
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20); // S = sale, P = purchase, TR = transfer, ...
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'type']);
        });

        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('status', 10)->default('open')->index(); // open, closed
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->string('opening_note')->nullable();
            $table->string('closing_note')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('invoice_no', 30)->nullable()->unique(); // null while on hold
            $table->string('status', 15)->default('completed')->index(); // completed, hold
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('cash_register_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('discount_type', 10)->default('amount'); // amount, percent
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);  // whole-bill discount in Rs
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->string('payment_status', 10)->default('paid')->index(); // paid, partial, due
            $table->string('note')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'sold_at']);
            $table->index(['user_id', 'sold_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->string('sku', 50)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('purchase_price', 12, 2); // cost snapshot for profit
            $table->decimal('discount', 12, 2)->default(0); // line discount in Rs
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('type', 30)->index(); // sale, customer_receipt, supplier_payment, refund...
            $table->nullableMorphs('payable');   // e.g. the Sale this payment belongs to
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 20);        // cash, card, jazzcash, easypaisa, bank
            $table->decimal('amount', 12, 2);
            $table->string('reference', 100)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('cash_register_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['branch_id', 'paid_at']);
            $table->index(['cash_register_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('cash_registers');
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('customers');
    }
};
