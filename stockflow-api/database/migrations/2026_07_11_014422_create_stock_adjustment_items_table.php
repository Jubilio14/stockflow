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
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('stock_adjustment_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->unsignedInteger('system_stock');

            $table
                ->unsignedInteger('actual_stock');

            $table
                ->integer('quantity_change');

            $table
                ->decimal(
                    'average_cost_before',
                    15,
                    4
                );

            $table
                ->decimal(
                    'average_cost_after',
                    15,
                    4
                );

            $table
                ->decimal(
                    'inventory_value_change',
                    15,
                    4
                );

            $table->timestamps();

            $table->unique([
                'stock_adjustment_id',
                'product_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
    }
};
