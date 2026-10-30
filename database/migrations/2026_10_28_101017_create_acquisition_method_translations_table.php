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
        Schema::create('acquisition_method_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('acquisition_method_id')
                ->constrained('acquisition_methods')
                ->cascadeOnDelete();

            $table->string('locale', 5);

            $table->string('name', 100);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['acquisition_method_id', 'locale'], 'acq_method_locale_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acquisition_method_translations');
    }
};
