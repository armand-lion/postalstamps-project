<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('
            ALTER TABLE catalog_condition_rules
            ADD COLUMN country_id_unique BIGINT UNSIGNED
                GENERATED ALWAYS AS (COALESCE(country_id, 0)) VIRTUAL,
            ADD COLUMN issue_year_from_unique SMALLINT UNSIGNED
                GENERATED ALWAYS AS (COALESCE(issue_year_from, 0)) VIRTUAL,
            ADD COLUMN issue_year_to_unique SMALLINT UNSIGNED
                GENERATED ALWAYS AS (COALESCE(issue_year_to, 0)) VIRTUAL
        ');

        DB::statement('
            ALTER TABLE catalog_condition_rules
            ADD CONSTRAINT catalog_condition_rules_unique
            UNIQUE (
                catalog_id,
                country_id_unique,
                condition_id,
                issue_year_from_unique,
                issue_year_to_unique
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            ALTER TABLE catalog_condition_rules
            DROP INDEX catalog_condition_rules_unique
        ');

        DB::statement('
            ALTER TABLE catalog_condition_rules
            DROP COLUMN country_id_unique,
            DROP COLUMN issue_year_from_unique,
            DROP COLUMN issue_year_to_unique
        ');
    }
};
