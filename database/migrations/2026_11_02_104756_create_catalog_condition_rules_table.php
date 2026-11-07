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
        Schema::create('catalog_condition_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('catalog_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('country_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('condition_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedSmallInteger('issue_year_from')->nullable();
            $table->unsignedSmallInteger('issue_year_to')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(
                ['catalog_id', 'country_id', 'issue_year_from', 'issue_year_to'],
                'catalog_condition_rules_lookup_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_condition_rules');
    }
};
