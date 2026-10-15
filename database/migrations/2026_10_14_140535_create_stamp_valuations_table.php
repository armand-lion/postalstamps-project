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
        Schema::create('stamp_valuations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stamp_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('condition_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->date('valuation_date');

            $table->decimal('value', 12, 2);
            $table->char('currency', 3)->default('EUR');

            $table->string('source', 150)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['stamp_id', 'valuation_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_valuations');
    }
};
