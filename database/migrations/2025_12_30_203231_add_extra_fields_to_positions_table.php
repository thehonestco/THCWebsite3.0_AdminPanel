<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {

            // Job meta
            $table->enum('job_type', ['Full Time', 'Part Time', 'Contract'])
                  ->after('organization_name');

            $table->enum('work_mode', ['Onsite', 'Remote', 'Hybrid'])
                  ->after('job_type');

            // Experience
            $table->unsignedTinyInteger('experience_min')
                  ->nullable()
                  ->after('work_mode');

            $table->unsignedTinyInteger('experience_max')
                  ->nullable()
                  ->after('experience_min');

            // Salary (LPA)
            $table->unsignedInteger('salary_min')
                  ->nullable()
                  ->after('experience_max');

            $table->unsignedInteger('salary_max')
                  ->nullable()
                  ->after('salary_min');

            // Reference code (L009 etc.)
            $table->string('reference_code', 20)
                  ->unique()
                  ->after('salary_max');
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn([
                'job_type',
                'work_mode',
                'experience_min',
                'experience_max',
                'salary_min',
                'salary_max',
                'reference_code',
            ]);
        });
    }
};
