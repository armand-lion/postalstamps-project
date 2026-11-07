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
        Schema::table('stamp_copies', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')
                ->nullable()
                ->after('id');
        });

        // Bestaande verzamelexemplaren zijn bevestigd als eigendom
        // van gebruiker 1.
        $existingCopies = DB::table('stamp_copies')->count();

        if ($existingCopies > 0) {
            if (! DB::table('users')->where('id', 1)->exists()) {
                throw new RuntimeException(
                    'Gebruiker 1 bestaat niet. Bestaande verzamelexemplaren kunnen niet worden gekoppeld.'
                );
            }

            DB::table('stamp_copies')
                ->whereNull('user_id')
                ->update(['user_id' => 1]);
        }

        Schema::table('stamp_copies', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')
                ->nullable(false)
                ->change();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stamp_copies', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
