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
        Schema::create('catalog_editions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('catalog_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('edition_name', 150);
            $table->unsignedSmallInteger('edition_year');

            $table->date('publication_date')->nullable();
            $table->char('currency', 3)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['catalog_id', 'edition_name', 'edition_year'],
                'catalog_editions_catalog_name_year_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_editions');
    }
};
