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
        Schema::create('collectible_stamps', function (Blueprint $table) {
            $table->id();

            // Koppeling naar het verzamelobject
            $table->foreignId('collectible_id')
                ->constrained('collectibles')
                ->cascadeOnDelete();

            // Koppeling naar de postzegel
            $table->foreignId('stamp_id')
                ->constrained('stamps')
                ->cascadeOnDelete();

            $table->timestamps();

            // Voorkom dubbele koppelingen
            $table->unique(
                ['collectible_id', 'stamp_id'],
                'collectible_stamps_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collectible_stamps');
    }
};
