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
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            // Koppel de waarde aan een catalogusvermelding.
            $table->foreignId('stamp_catalog_entry_id')
                ->after('id')
                ->constrained('stamp_catalog_entries')
                ->restrictOnDelete();
        });

        // Verwijder eerst de indexen die de oude kolommen gebruiken.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->dropIndex('stamp_catalog_values_edition_stamp_index');
            $table->dropIndex('stamp_catalog_values_catalog_number_index');
        });

        // Verwijder daarna de oude foreign keys en kolommen.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->dropForeign(['catalog_edition_id']);
            $table->dropForeign(['stamp_id']);
            $table->dropColumn([
                'catalog_edition_id',
                'stamp_id',
                'catalog_number',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Herstel de oude kolommen.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->string('catalog_number', 100)->after('condition_id');

            $table->foreignId('catalog_edition_id')
                ->nullable()
                ->after('id')
                ->constrained('catalog_editions')
                ->restrictOnDelete();

            $table->foreignId('stamp_id')
                ->nullable()
                ->after('catalog_edition_id')
                ->constrained('stamps')
                ->restrictOnDelete();
        });

        // Herstel de oorspronkelijke indexen.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->index(
                ['catalog_edition_id', 'stamp_id'],
                'stamp_catalog_values_edition_stamp_index'
            );

            $table->index('catalog_number');
        });

        // Verwijder de nieuwe koppeling aan de catalogusvermelding.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->dropForeign(['stamp_catalog_entry_id']);
            $table->dropColumn('stamp_catalog_entry_id');
        });
    }
};
