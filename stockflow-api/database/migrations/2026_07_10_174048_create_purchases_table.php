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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();

            $table
                ->string('purchase_number', 50)
                ->unique();

            $table
                ->foreignId('supplier_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table
                ->string('invoice_number', 100)
                ->nullable();

            $table->date('purchase_date');

            $table
                ->decimal('total_amount', 15, 2)
                ->default(0);

            $table
                ->string('status', 30)
                ->default('completed')
                ->index();

            $table
                ->text('notes')
                ->nullable();

            $table->timestamps();

            $table->index([
                'supplier_id',
                'purchase_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
