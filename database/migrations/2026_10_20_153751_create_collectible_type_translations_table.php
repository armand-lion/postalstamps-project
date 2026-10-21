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
        Schema::create('collectible_type_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('collectible_type_id')
                ->constrained('collectible_types')
                ->cascadeOnDelete();

            $table->string('locale', 5);

            $table->string('name', 150);

            $table->timestamps();

            $table->unique(
                ['collectible_type_id', 'locale'],
                'collectible_type_translations_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collectible_type_translations');
    }
};
