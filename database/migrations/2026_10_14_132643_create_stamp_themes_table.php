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
        Schema::create('stamp_themes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stamp_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('theme_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['stamp_id', 'theme_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_themes');
    }
};
