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
        Schema::create('watermark_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('watermark_id')
                ->constrained('watermarks')
                ->cascadeOnDelete();

            $table->string('locale', 5);

            $table->string('name', 100);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['watermark_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watermark_translations');
    }
};
