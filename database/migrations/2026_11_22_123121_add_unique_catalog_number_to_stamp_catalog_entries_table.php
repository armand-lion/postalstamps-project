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
        Schema::table('stamp_catalog_entries', function (Blueprint $table) {
            // Een catalogusnummer mag slechts één keer
            // binnen dezelfde cataloguseditie voorkomen.
            $table->unique(
                ['catalog_edition_id', 'catalog_number'],
                'stamp_catalog_entries_edition_number_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stamp_catalog_entries', function (Blueprint $table) {
            // Verwijder uitsluitend de toegevoegde unieke beperking.
            $table->dropUnique(
                'stamp_catalog_entries_edition_number_unique'
            );
        });
    }
};
