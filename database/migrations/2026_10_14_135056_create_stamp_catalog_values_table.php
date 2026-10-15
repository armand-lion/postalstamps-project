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
        Schema::create('stamp_catalog_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('catalog_edition_id')
                  ->constrained()
                  ->restrictOnDelete();

            $table->foreignId('stamp_id')
                  ->constrained()
                  ->restrictOnDelete();

            $table->foreignId('condition_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            $table->string('catalog_number', 100);

            $table->decimal('catalog_value', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(
                ['catalog_edition_id', 'stamp_id'],
                'stamp_catalog_values_edition_stamp_index'
            );

            $table->index('catalog_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_catalog_values');
    }
};
