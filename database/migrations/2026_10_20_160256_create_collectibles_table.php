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
        Schema::create('collectibles', function (Blueprint $table) {
            $table->id();

            // Type verzamelobject (FDC, boekje, souvenirvel, enz.)
            $table->foreignId('collectible_type_id')
                ->constrained('collectible_types')
                ->restrictOnDelete();

            // Land van uitgifte
            $table->foreignId('country_id')
                ->constrained('countries')
                ->restrictOnDelete();

            // Optionele koppeling aan een postzegelserie
            $table->foreignId('stamp_series_id')
                ->nullable()
                ->constrained('stamp_series')
                ->nullOnDelete();

            // Algemene gegevens
            $table->string('name', 200);
            $table->text('description')->nullable();

            // Uitgiftegegevens
            $table->unsignedSmallInteger('issue_year');
            $table->date('issue_date')->nullable();

            // Overige informatie
            $table->text('notes')->nullable();

            $table->timestamps();

            // Sneller zoeken op land, jaar en type
            $table->index(
                ['country_id', 'issue_year', 'collectible_type_id'],
                'collectibles_country_year_type_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collectibles');
    }
};
