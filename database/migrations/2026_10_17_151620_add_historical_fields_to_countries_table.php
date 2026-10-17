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
        Schema::table('countries', function (Blueprint $table) {
            $table->string('entity_type', 30)
                ->default('country')
                ->after('iso_code');

            $table->unsignedSmallInteger('start_year')
                ->nullable()
                ->after('entity_type');

            $table->unsignedSmallInteger('end_year')
                ->nullable()
                ->after('start_year');

            $table->boolean('is_active')
                ->default(true)
                ->after('end_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn([
                'entity_type',
                'start_year',
                'end_year',
                'is_active',
            ]);
        });
    }
};
