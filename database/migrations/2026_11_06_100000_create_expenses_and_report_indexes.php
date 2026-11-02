<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('color', 7)->default('#4F46E5');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_no', 30)->unique();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('method', 20);
            $table->string('paid_to')->nullable();
            $table->string('note')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('cash_register_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'expense_date']);
        });

        // Report helpers
        Schema::table('sale_items', fn (Blueprint $t) => $t->index(['product_id', 'sale_id']));
        Schema::table('sales', fn (Blueprint $t) => $t->index(['status', 'sold_at']));
        Schema::table('purchases', fn (Blueprint $t) => $t->index(['status', 'received_at']));
        Schema::table('payments', fn (Blueprint $t) => $t->index(['type', 'paid_at']));
        Schema::table('activity_logs', fn (Blueprint $t) => $t->index(['user_id', 'created_at']));
    }

    public function down(): void
    {
        Schema::table('activity_logs', fn (Blueprint $t) => $t->dropIndex(['user_id', 'created_at']));
        Schema::table('payments', fn (Blueprint $t) => $t->dropIndex(['type', 'paid_at']));
        Schema::table('purchases', fn (Blueprint $t) => $t->dropIndex(['status', 'received_at']));
        Schema::table('sales', fn (Blueprint $t) => $t->dropIndex(['status', 'sold_at']));
        Schema::table('sale_items', fn (Blueprint $t) => $t->dropIndex(['product_id', 'sale_id']));
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
