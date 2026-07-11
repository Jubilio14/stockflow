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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table
                ->string('name', 100);

            $table
                ->string('code', 50)
                ->unique();

            $table
                ->string('discount_type', 20)
                ->index();

            $table
                ->decimal('discount_value', 15, 2);

            $table
                ->decimal('minimum_purchase', 15, 2)
                ->default(0);

            $table
                ->decimal('maximum_discount', 15, 2)
                ->nullable();

            $table
                ->dateTime('starts_at');

            $table
                ->dateTime('ends_at');

            $table
                ->boolean('is_active')
                ->default(true)
                ->index();

            $table->timestamps();

            $table->index([
                'is_active',
                'starts_at',
                'ends_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
