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
        Schema::create('collectible_images', function (Blueprint $table) {
            $table->id();

            // Koppeling naar het verzamelobject
            $table->foreignId('collectible_id')
                ->constrained('collectibles')
                ->cascadeOnDelete();

            // Relatief bestandspad in Laravel Storage
            $table->string('path');

            // Type afbeelding, bijvoorbeeld front of back
            $table->string('image_type')->default('front');

            // Alternatieve tekst voor toegankelijkheid
            $table->string('alt_text')->nullable();

            // Primaire afbeelding van het verzamelobject
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            // Index voor het opvragen van afbeeldingen per type
            $table->index(
                ['collectible_id', 'image_type'],
                'collectible_images_type_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collectible_images');
    }
};
