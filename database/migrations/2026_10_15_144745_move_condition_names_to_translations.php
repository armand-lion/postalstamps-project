<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Bewaar de bestaande Nederlandse namen en beschrijvingen.
        $conditions = DB::table('conditions')
            ->select('id', 'name', 'description')
            ->get();

        foreach ($conditions as $condition) {
            DB::table('condition_translations')->updateOrInsert(
                [
                    'condition_id' => $condition->id,
                    'locale' => 'nl',
                ],
                [
                    'name' => $condition->name,
                    'description' => $condition->description,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // Verwijder de unieke index en de taalafhankelijke kolommen.
        Schema::table('conditions', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropColumn(['name', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Herstel de kolommen bij het terugdraaien van de migratie.
        Schema::table('conditions', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->unique();
            $table->text('description')->nullable();
        });

        // Zet de Nederlandse gegevens terug.
        $translations = DB::table('condition_translations')
            ->where('locale', 'nl')
            ->get();

        foreach ($translations as $translation) {
            DB::table('conditions')
                ->where('id', $translation->condition_id)
                ->update([
                    'name' => $translation->name,
                    'description' => $translation->description,
                ]);
        }
    }
};
