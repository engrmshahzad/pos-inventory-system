<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom roles behave like a manager (whole branch) or a cashier (own sales only).
     * The code checks this type, never the custom role's name.
     */
    public function up(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->string('base_type', 20)->default('cashier')->after('guard_name');
        });

        foreach (['owner', 'manager', 'cashier'] as $type) {
            DB::table(config('permission.table_names.roles'))->where('name', $type)->update(['base_type' => $type]);
        }
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->dropColumn('base_type');
        });
    }
};
