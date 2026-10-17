<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamp_series_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stamp_series_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('locale', 5);
            $table->string('name', 255);
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['stamp_series_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_series_translations');
    }
};
