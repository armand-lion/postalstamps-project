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
        Schema::create('collectible_catalog_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('collectible_catalog_entry_id')
                ->constrained('collectible_catalog_entries')
                ->restrictOnDelete();

            $table->foreignId('condition_id')
                ->nullable()
                ->constrained('conditions')
                ->nullOnDelete();

            $table->decimal('catalog_value', 12, 2)->nullable();

            $table->char('currency', 3)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collectible_catalog_values');
    }
};
