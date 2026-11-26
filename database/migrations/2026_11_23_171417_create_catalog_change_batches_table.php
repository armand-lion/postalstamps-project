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
        Schema::create('catalog_change_batches', function (Blueprint $table) {
            $table->id();

            // Cataloguseditie waarop de bewerking betrekking heeft.
            $table->foreignId('catalog_edition_id')
                ->nullable()
                ->constrained('catalog_editions')
                ->nullOnDelete();

            // Gebruiker die de bewerking heeft uitgevoerd.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Historische gebruikersnaam.
            $table->string('user_name')->nullable();

            // Type bewerking: manual, bulk_edit, import_create,
            // import_merge of import_replace.
            $table->string('operation', 50);

            // Optionele toelichting bij de bewerking.
            $table->text('description')->nullable();

            // Historische identificatie van de cataloguseditie.
            $table->string('catalog_name')->nullable();
            $table->string('catalog_edition_name')->nullable();

            $table->timestamps();

            // Ondersteuning voor het opzoeken van wijzigingen.
            $table->index(
                ['catalog_edition_id', 'created_at'],
                'catalog_change_batches_edition_date_index'
            );

            $table->index(
                ['user_id', 'created_at'],
                'catalog_change_batches_user_date_index'
            );

            $table->index('operation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_change_batches');
    }
};
