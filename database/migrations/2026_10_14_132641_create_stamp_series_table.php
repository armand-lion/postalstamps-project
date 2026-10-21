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
        Schema::create('stamp_series', function (Blueprint $table) {
            $table->id();

            $table->foreignId('country_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name', 200);
            $table->unsignedSmallInteger('issue_year')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['country_id', 'issue_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_series');
    }
};
