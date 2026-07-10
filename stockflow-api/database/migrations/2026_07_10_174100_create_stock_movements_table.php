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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table
                ->string('type', 30)
                ->index();

            $table
                ->string('reference_type', 50)
                ->nullable();

            $table
                ->unsignedBigInteger('reference_id')
                ->nullable();

            $table
                ->integer('quantity_change');

            $table
                ->unsignedInteger('stock_before');

            $table
                ->unsignedInteger('stock_after');

            $table
                ->decimal('unit_cost', 15, 4)
                ->nullable();

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
                ->text('notes')
                ->nullable();

            $table->timestamp('movement_at');

            $table->timestamps();

            $table->index([
                'reference_type',
                'reference_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
