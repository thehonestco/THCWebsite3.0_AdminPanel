<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // 1️⃣ Temporarily allow stage as VARCHAR
        DB::statement("
            ALTER TABLE applications 
            MODIFY stage VARCHAR(50)
        ");

        // 2️⃣ Map old values → new values
        DB::table('applications')->where('stage', 'open')
            ->update(['stage' => 'fresh']);

        DB::table('applications')->where('stage', 'interview')
            ->update(['stage' => 'tech_round']);

        DB::table('applications')->where('stage', 'offer')
            ->update(['stage' => 'offer_sent']);

        DB::table('applications')->where('stage', 'hired')
            ->update(['stage' => 'final_round']);

        // screening & rejected remain same

        // 3️⃣ Convert to NEW ENUM
        DB::statement("
            ALTER TABLE applications 
            MODIFY stage ENUM(
                'fresh',
                'screening',
                'hr_round',
                'tech_round',
                'final_round',
                'offer_sent',
                'rejected',
                'dropped'
            ) NOT NULL DEFAULT 'fresh'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE applications 
            MODIFY stage ENUM(
                'open',
                'screening',
                'interview',
                'offer',
                'hired',
                'rejected'
            ) NOT NULL DEFAULT 'open'
        ");
    }
};
