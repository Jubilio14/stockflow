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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('category_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name');

            $table
                ->string('sku')
                ->unique();

            $table
                ->string('barcode')
                ->nullable()
                ->unique();

            $table
                ->string('unit', 30)
                ->default('pcs');

            $table
                ->decimal('selling_price', 15, 2)
                ->default(0);

            $table
                ->decimal('average_cost', 15, 4)
                ->default(0);

            $table
                ->unsignedInteger('current_stock')
                ->default(0);

            $table
                ->unsignedInteger('minimum_stock')
                ->default(0);

            $table
                ->string('image_path')
                ->nullable();

            $table
                ->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->index([
                'category_id',
                'is_active',
            ]);

            $table->index('current_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
