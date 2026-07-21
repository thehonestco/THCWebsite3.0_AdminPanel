<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE resources
            SET sub_industry = JSON_ARRAY(sub_industry)
            WHERE sub_industry IS NOT NULL
              AND JSON_VALID(sub_industry) = 0
        ");

        DB::statement("
            UPDATE resources
            SET sub_service = JSON_ARRAY(sub_service)
            WHERE sub_service IS NOT NULL
              AND JSON_VALID(sub_service) = 0
        ");

        DB::statement('ALTER TABLE resources MODIFY sub_industry JSON NULL');
        DB::statement('ALTER TABLE resources MODIFY sub_service JSON NULL');
    }

    public function down(): void
    {
        DB::statement("
            UPDATE resources
            SET sub_industry = JSON_UNQUOTE(JSON_EXTRACT(sub_industry, '$[0]'))
            WHERE sub_industry IS NOT NULL
        ");

        DB::statement("
            UPDATE resources
            SET sub_service = JSON_UNQUOTE(JSON_EXTRACT(sub_service, '$[0]'))
            WHERE sub_service IS NOT NULL
        ");

        DB::statement('ALTER TABLE resources MODIFY sub_industry VARCHAR(100) NULL');
        DB::statement('ALTER TABLE resources MODIFY sub_service VARCHAR(100) NULL');
    }
};
