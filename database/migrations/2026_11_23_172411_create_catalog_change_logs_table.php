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
        Schema::create('catalog_change_logs', function (Blueprint $table) {
            $table->id();

            // Batch waartoe deze wijziging behoort.
            $table->foreignId('catalog_change_batch_id')
                ->constrained('catalog_change_batches')
                ->restrictOnDelete();

            // Historische identificatie van de cataloguseditie.
            $table->unsignedBigInteger('catalog_edition_id')->nullable();

            // Historische identificatie van de postzegel.
            $table->unsignedBigInteger('stamp_id')->nullable();

            // Historische identificatie van de postzegelserie.
            $table->unsignedBigInteger('stamp_series_id')->nullable();

            // Historische identificatie van de waarderingscategorie.
            $table->unsignedBigInteger('condition_id')->nullable();

            // Modeltype: stamp_catalog_entry, stamp_catalog_value
            // of series_catalog_value.
            $table->string('record_type', 50);

            // Oorspronkelijke database-ID van het gewijzigde record.
            $table->unsignedBigInteger('record_id');

            // Soort wijziging: created, updated of deleted.
            $table->string('action', 20);

            // Gegevens vóór de wijziging.
            $table->json('old_values')->nullable();

            // Gegevens na de wijziging.
            $table->json('new_values')->nullable();

            $table->timestamps();

            // Geschiedenis per cataloguseditie.
            $table->index(
                ['catalog_edition_id', 'created_at'],
                'catalog_change_logs_edition_date_index'
            );

            // Geschiedenis per postzegel.
            $table->index(
                ['stamp_id', 'created_at'],
                'catalog_change_logs_stamp_date_index'
            );

            // Geschiedenis per postzegelserie.
            $table->index(
                ['stamp_series_id', 'created_at'],
                'catalog_change_logs_series_date_index'
            );

            // Geschiedenis van een specifiek catalogusrecord.
            $table->index(
                ['record_type', 'record_id'],
                'catalog_change_logs_record_index'
            );

            // Wijzigingen binnen een batch.
            $table->index(
                ['catalog_change_batch_id', 'created_at'],
                'catalog_change_logs_batch_date_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_change_logs');
    }
};
