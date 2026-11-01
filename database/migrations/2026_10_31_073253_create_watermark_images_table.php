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
        Schema::create('watermark_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('watermark_id')
                ->constrained('watermarks')
                ->cascadeOnDelete();

            $table->string('path');

            $table->string('alt_text')->nullable();

            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index('watermark_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watermark_images');
    }
};
