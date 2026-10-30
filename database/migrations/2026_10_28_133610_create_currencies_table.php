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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();

            // Internationale valutacode, bijvoorbeeld EUR, USD of NLG.
            $table->string('code', 3)->unique();

            // Volgorde in de keuzelijst.
            $table->unsignedInteger('sort_order')->default(0);

            // Valuta beschikbaar maken in de keuzelijst.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
