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
        Schema::create('stamp_copies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stamp_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('condition_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->date('acquisition_date')->nullable();

            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->char('purchase_currency', 3)->nullable();

            $table->string('acquisition_method', 100)->nullable();

            $table->string('watermark', 150)->nullable();
            $table->string('perforation', 150)->nullable();
            $table->string('gum_condition', 100)->nullable();

            $table->boolean('is_variant')->default(false);
            $table->string('variant_description', 255)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['stamp_id', 'condition_id']);
            $table->index('acquisition_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_copies');
    }
};
