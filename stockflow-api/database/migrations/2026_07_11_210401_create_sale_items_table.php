<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('sale_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->unsignedInteger('quantity');

            /*
            |--------------------------------------------------------------------------
            | Snapshot harga
            |--------------------------------------------------------------------------
            */

            $table
                ->decimal('selling_price', 15, 2);

            $table
                ->decimal('average_cost', 15, 4);

            /*
            |--------------------------------------------------------------------------
            | Perhitungan penjualan
            |--------------------------------------------------------------------------
            */

            $table
                ->decimal('subtotal', 15, 2);

            $table
                ->decimal('allocated_discount', 15, 2)
                ->default(0);

            $table
                ->decimal('net_sales', 15, 2);

            $table
                ->decimal('cost_total', 15, 4);

            $table
                ->decimal('gross_profit', 15, 4);

            /*
            |--------------------------------------------------------------------------
            | Snapshot stok
            |--------------------------------------------------------------------------
            */

            $table
                ->unsignedInteger('stock_before');

            $table
                ->unsignedInteger('stock_after');

            $table->timestamps();

            $table->unique([
                'sale_id',
                'product_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
