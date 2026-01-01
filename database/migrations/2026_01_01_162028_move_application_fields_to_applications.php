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
        /**
         * 1️⃣ ADD application-level fields to applications
         */
        Schema::table('applications', function (Blueprint $table) {
            $table->decimal('experience_years', 4, 1)
                  ->nullable()
                  ->after('applicant_id');

            $table->unsignedInteger('current_ctc')
                  ->nullable()
                  ->after('experience_years');

            $table->unsignedInteger('expected_ctc')
                  ->nullable()
                  ->after('current_ctc');

            $table->unsignedInteger('notice_period_days')
                  ->nullable()
                  ->after('expected_ctc');

            $table->timestamp('last_contact_at')
                  ->nullable()
                  ->after('stage');
        });

        /**
         * 2️⃣ REMOVE wrongly placed fields from applicants
         */
        Schema::table('applicants', function (Blueprint $table) {
            if (Schema::hasColumn('applicants', 'experience_years')) {
                $table->dropColumn([
                    'experience_years',
                    'current_ctc',
                    'expected_ctc',
                    'notice_period_days',
                    'last_contact_at',
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /**
         * 1️⃣ RESTORE fields back to applicants
         */
        Schema::table('applicants', function (Blueprint $table) {
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->unsignedInteger('current_ctc')->nullable();
            $table->unsignedInteger('expected_ctc')->nullable();
            $table->unsignedInteger('notice_period_days')->nullable();
            $table->timestamp('last_contact_at')->nullable();
        });

        /**
         * 2️⃣ REMOVE fields from applications
         */
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'experience_years',
                'current_ctc',
                'expected_ctc',
                'notice_period_days',
                'last_contact_at',
            ]);
        });
    }
};
