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
        Schema::create('stamp_copy_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stamp_copy_id')
                ->constrained('stamp_copies')
                ->cascadeOnDelete();

            $table->string('locale', 5);

            $table->text('variant_description')->nullable();

            $table->timestamps();

            $table->unique(
                ['stamp_copy_id', 'locale'],
                'stamp_copy_translations_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_copy_translations');
    }
};
