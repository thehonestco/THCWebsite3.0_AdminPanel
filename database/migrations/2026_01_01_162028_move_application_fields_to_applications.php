<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('applications', 'experience_years')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->decimal('experience_years', 4, 1)->nullable()->after('applicant_id');
            });
        }

        if (!Schema::hasColumn('applications', 'current_ctc')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->unsignedInteger('current_ctc')->nullable()->after('experience_years');
            });
        }

        if (!Schema::hasColumn('applications', 'expected_ctc')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->unsignedInteger('expected_ctc')->nullable()->after('current_ctc');
            });
        }

        if (!Schema::hasColumn('applications', 'notice_period_days')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->unsignedInteger('notice_period_days')->nullable()->after('expected_ctc');
            });
        }

        if (!Schema::hasColumn('applications', 'last_contact_at')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->timestamp('last_contact_at')->nullable()->after('stage');
            });
        }

        $applicantColumnsToDrop = array_values(array_filter([
            Schema::hasColumn('applicants', 'experience_years') ? 'experience_years' : null,
            Schema::hasColumn('applicants', 'current_ctc') ? 'current_ctc' : null,
            Schema::hasColumn('applicants', 'expected_ctc') ? 'expected_ctc' : null,
            Schema::hasColumn('applicants', 'notice_period_days') ? 'notice_period_days' : null,
            Schema::hasColumn('applicants', 'last_contact_at') ? 'last_contact_at' : null,
        ]));

        if ($applicantColumnsToDrop !== []) {
            Schema::table('applicants', function (Blueprint $table) use ($applicantColumnsToDrop) {
                $table->dropColumn($applicantColumnsToDrop);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('applicants', 'experience_years')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->unsignedTinyInteger('experience_years')->nullable();
            });
        }

        if (!Schema::hasColumn('applicants', 'current_ctc')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->unsignedInteger('current_ctc')->nullable();
            });
        }

        if (!Schema::hasColumn('applicants', 'expected_ctc')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->unsignedInteger('expected_ctc')->nullable();
            });
        }

        if (!Schema::hasColumn('applicants', 'notice_period_days')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->unsignedInteger('notice_period_days')->nullable();
            });
        }

        if (!Schema::hasColumn('applicants', 'last_contact_at')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->timestamp('last_contact_at')->nullable();
            });
        }

        $applicationColumnsToDrop = array_values(array_filter([
            Schema::hasColumn('applications', 'experience_years') ? 'experience_years' : null,
            Schema::hasColumn('applications', 'current_ctc') ? 'current_ctc' : null,
            Schema::hasColumn('applications', 'expected_ctc') ? 'expected_ctc' : null,
            Schema::hasColumn('applications', 'notice_period_days') ? 'notice_period_days' : null,
            Schema::hasColumn('applications', 'last_contact_at') ? 'last_contact_at' : null,
        ]));

        if ($applicationColumnsToDrop !== []) {
            Schema::table('applications', function (Blueprint $table) use ($applicationColumnsToDrop) {
                $table->dropColumn($applicationColumnsToDrop);
            });
        }
    }
};
