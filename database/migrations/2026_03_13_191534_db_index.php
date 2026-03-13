<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 4. Database Indexing
     * products.name — missing index (used in LIKE search)
     * orders.status — missing index (used in WHERE filter)
     * products.sold_count — missing index (used in ORDER BY)
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('name');
            $table->index('description');
            $table->index('sold_count');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_name_index');
            $table->dropIndex('products_description_index');
            $table->dropIndex('products_sold_count_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_index');
        });
    }
};
