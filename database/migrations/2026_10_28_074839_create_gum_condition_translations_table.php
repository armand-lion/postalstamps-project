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
        Schema::create('gum_condition_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gum_condition_id')
                ->constrained('gum_conditions')
                ->cascadeOnDelete();

            $table->string('locale', 5);

            $table->string('name', 100);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['gum_condition_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gum_condition_translations');
    }
};
