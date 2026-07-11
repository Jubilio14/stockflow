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
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();

            $table
                ->string('session_number', 50)
                ->unique();

            $table
                ->foreignId('cashier_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->dateTime('opened_at');

            $table
                ->decimal('opening_cash', 15, 2);

            $table
                ->decimal('cash_sales_total', 15, 2)
                ->default(0);

            $table
                ->dateTime('closed_at')
                ->nullable();

            $table
                ->decimal('expected_closing_cash', 15, 2)
                ->nullable();

            $table
                ->decimal('actual_closing_cash', 15, 2)
                ->nullable();

            $table
                ->decimal('difference', 15, 2)
                ->nullable();

            $table
                ->string('status', 20)
                ->default('open')
                ->index();

            $table
                ->string('open_guard', 30)
                ->nullable()
                ->unique();

            $table
                ->text('opening_notes')
                ->nullable();

            $table
                ->text('closing_notes')
                ->nullable();

            $table->timestamps();

            $table->index([
                'cashier_id',
                'opened_at',
            ]);

            $table->index([
                'status',
                'opened_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
