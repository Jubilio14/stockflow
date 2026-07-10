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
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();

            $table
                ->string('adjustment_number', 50)
                ->unique();

            $table
                ->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('adjustment_date');

            $table
                ->string('reason', 30)
                ->index();

            $table
                ->string('status', 30)
                ->default('completed')
                ->index();

            $table
                ->text('notes')
                ->nullable();

            $table->timestamps();

            $table->index([
                'adjustment_date',
                'created_by',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
