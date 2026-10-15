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
        Schema::create('stamp_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stamp_id')
                ->constrained('stamps')
                ->cascadeOnDelete();

            $table->string('path');
            $table->string('image_type')->default('front');
            $table->string('alt_text')->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index(['stamp_id', 'image_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_images');
    }
};
