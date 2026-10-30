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
        Schema::table('currency_translations', function (Blueprint $table) {
            $table->foreignId('currency_id')
                ->after('id')
                ->constrained('currencies')
                ->cascadeOnDelete();

            $table->string('locale', 5)->after('currency_id');
            $table->string('name')->after('locale');

            $table->unique(['currency_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currency_translations', function (Blueprint $table) {
            $table->dropUnique(['currency_id', 'locale']);
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['currency_id', 'locale', 'name']);
        });
    }
};
