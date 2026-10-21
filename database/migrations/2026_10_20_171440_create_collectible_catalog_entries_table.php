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
        Schema::create('collectible_catalog_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('catalog_edition_id')
                ->constrained('catalog_editions')
                ->restrictOnDelete();

            $table->foreignId('collectible_id')
                ->constrained('collectibles')
                ->restrictOnDelete();

            $table->string('catalog_number', 100);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['catalog_edition_id', 'collectible_id'],
                'collectible_catalog_entries_edition_collectible_unique'
            );

            $table->index(
                ['catalog_edition_id', 'catalog_number'],
                'collectible_catalog_entries_edition_number_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collectible_catalog_entries');
    }
};
