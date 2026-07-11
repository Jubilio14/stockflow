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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table
                ->string('sale_number', 50)
                ->unique();

            $table
                ->foreignId('cash_session_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->foreignId('cashier_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table
                ->foreignId('promotion_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Snapshot promo
            |--------------------------------------------------------------------------
            |
            | Data ini tetap disimpan meskipun informasi promo nantinya diedit.
            |
            */

            $table
                ->string('promotion_name', 100)
                ->nullable();

            $table
                ->string('promotion_code', 50)
                ->nullable();

            $table
                ->string('promotion_discount_type', 20)
                ->nullable();

            $table
                ->decimal(
                    'promotion_discount_value',
                    15,
                    2
                )
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Nilai transaksi
            |--------------------------------------------------------------------------
            */

            $table
                ->decimal('subtotal', 15, 2);

            $table
                ->decimal('discount_amount', 15, 2)
                ->default(0);

            $table
                ->decimal('total_amount', 15, 2);

            $table
                ->decimal('total_cost', 15, 4);

            $table
                ->decimal('gross_profit', 15, 4);

            /*
            |--------------------------------------------------------------------------
            | Pembayaran
            |--------------------------------------------------------------------------
            */

            $table
                ->string('payment_method', 20)
                ->index();

            $table
                ->decimal('amount_paid', 15, 2);

            $table
                ->decimal('change_amount', 15, 2)
                ->default(0);

            $table
                ->string('status', 20)
                ->default('completed')
                ->index();

            $table->dateTime('sold_at');

            $table
                ->text('notes')
                ->nullable();

            $table->timestamps();

            $table->index([
                'cash_session_id',
                'sold_at',
            ]);

            $table->index([
                'cashier_id',
                'sold_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
