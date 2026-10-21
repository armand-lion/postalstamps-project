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
        Schema::create('stamps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('country_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('stamp_series_id')
                ->nullable()
                ->constrained('stamp_series')
                ->nullOnDelete();

            $table->string('name', 200);
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('issue_year');
            $table->date('issue_date')->nullable();

            $table->decimal('face_value', 12, 4)->nullable();
            $table->char('currency', 3)->nullable();

            $table->string('designer', 150)->nullable();
            $table->string('printing_method', 100)->nullable();

            $table->string('catalog_reference', 100)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['country_id', 'issue_year']);
            $table->index('stamp_series_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamps');
    }
};
