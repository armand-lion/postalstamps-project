<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collectible_copy_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('collectible_copy_id')
                ->constrained('collectible_copies')
                ->cascadeOnDelete();

            $table->string('locale', 5);

            $table->text('variant_description')->nullable();

            $table->timestamps();

            $table->unique(['collectible_copy_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collectible_copy_translations');
    }
};
