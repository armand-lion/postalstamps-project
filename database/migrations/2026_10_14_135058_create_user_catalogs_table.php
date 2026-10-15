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
        Schema::create('user_catalogs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('catalog_edition_id')
                ->constrained()
                ->restrictOnDelete();

            $table->date('acquisition_date')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->char('purchase_currency', 3)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['user_id', 'catalog_edition_id'],
                'user_catalogs_user_edition_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_catalogs');
    }
};
