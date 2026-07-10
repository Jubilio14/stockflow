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
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('purchase_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');

            $table->decimal('unit_cost', 15, 2);

            $table->decimal('subtotal', 15, 2);

            $table
                ->unsignedInteger('stock_before');

            $table
                ->unsignedInteger('stock_after');

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

            $table->timestamps();

            $table->unique([
                'purchase_id',
                'product_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
