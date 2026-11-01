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
        // Verwijder de bestaande foreign key met ON DELETE SET NULL.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->dropForeign(['condition_id']);
        });

        // Maak de conditie verplicht.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->unsignedBigInteger('condition_id')
                ->nullable(false)
                ->change();
        });

        // Herstel de foreign key met ON DELETE RESTRICT.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->foreign('condition_id')
                ->references('id')
                ->on('conditions')
                ->restrictOnDelete();
        });

        // Voorkom dubbele cataloguswaarden per conditie.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->unique(
                ['stamp_catalog_entry_id', 'condition_id'],
                'stamp_catalog_values_entry_condition_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Verwijder de unieke index.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->dropUnique(
                'stamp_catalog_values_entry_condition_unique'
            );
        });

        // Verwijder de foreign key met ON DELETE RESTRICT.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->dropForeign(['condition_id']);
        });

        // Sta opnieuw NULL toe.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->unsignedBigInteger('condition_id')
                ->nullable()
                ->change();
        });

        // Herstel de oorspronkelijke foreign key.
        Schema::table('stamp_catalog_values', function (Blueprint $table) {
            $table->foreign('condition_id')
                ->references('id')
                ->on('conditions')
                ->nullOnDelete();
        });
    }
};
