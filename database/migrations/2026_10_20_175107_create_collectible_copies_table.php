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
        Schema::create('collectible_copies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('collectible_id')
                ->constrained('collectibles')
                ->restrictOnDelete();

            $table->foreignId('condition_id')
                ->nullable()
                ->constrained('conditions')
                ->nullOnDelete();

            $table->date('acquisition_date')->nullable();

            $table->decimal('purchase_price', 12, 2)->nullable();

            $table->char('purchase_currency', 3)->nullable();

            $table->string('acquisition_method', 100)->nullable();

            $table->boolean('is_variant')->default(false);

            $table->string('variant_description', 255)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(
                ['collectible_id', 'condition_id'],
                'collectible_copies_collectible_condition_index'
            );

            $table->index(
                'acquisition_date',
                'collectible_copies_acquisition_date_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collectible_copies');
    }
};
