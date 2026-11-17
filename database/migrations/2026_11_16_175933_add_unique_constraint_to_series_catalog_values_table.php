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
        // Eerst de bestaande foreign key verwijderen.
        Schema::table('series_catalog_values', function (Blueprint $table) {
            $table->dropForeign(['condition_id']);
        });

        // De foreign key opnieuw toevoegen met RESTRICT.
        Schema::table('series_catalog_values', function (Blueprint $table) {
            $table->foreign('condition_id')
                ->references('id')
                ->on('conditions')
                ->restrictOnDelete();
        });

        // NULL als 0 behandelen voor de unieke combinatie.
        Schema::table('series_catalog_values', function (Blueprint $table) {
            $table->unsignedBigInteger('condition_id_unique')
                ->storedAs('COALESCE(condition_id, 0)');

            $table->unique(
                [
                    'catalog_edition_id',
                    'stamp_series_id',
                    'condition_id_unique',
                ],
                'series_catalog_values_edition_series_condition_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eerst de unieke index en gegenereerde kolom verwijderen.
        Schema::table('series_catalog_values', function (Blueprint $table) {
            $table->dropUnique(
                'series_catalog_values_edition_series_condition_unique'
            );

            $table->dropColumn('condition_id_unique');
        });

        // De RESTRICT-foreign key verwijderen.
        Schema::table('series_catalog_values', function (Blueprint $table) {
            $table->dropForeign(['condition_id']);
        });

        // De oorspronkelijke foreign key herstellen.
        Schema::table('series_catalog_values', function (Blueprint $table) {
            $table->foreign('condition_id')
                ->references('id')
                ->on('conditions')
                ->nullOnDelete();
        });
    }
};
