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
        Schema::create('stamp_catalog_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('catalog_edition_id')
                ->constrained('catalog_editions')
                ->restrictOnDelete();

            $table->foreignId('stamp_id')
                ->constrained('stamps')
                ->restrictOnDelete();

            $table->string('catalog_number', 100);
            $table->text('notes')->nullable();

            $table->timestamps();

            // Eén catalogusvermelding per postzegel per editie.
            $table->unique(
                ['catalog_edition_id', 'stamp_id'],
                'stamp_catalog_entries_edition_stamp_unique'
            );

            // Handig bij het zoeken op catalogusnummer binnen een editie.
            $table->index(
                ['catalog_edition_id', 'catalog_number'],
                'stamp_catalog_entries_edition_number_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_catalog_entries');
    }
};
