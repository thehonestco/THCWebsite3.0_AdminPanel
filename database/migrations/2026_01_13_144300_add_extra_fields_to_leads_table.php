<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leads', 'name') && !Schema::hasColumn('leads', 'poc_name')) {
            DB::statement('ALTER TABLE leads CHANGE name poc_name VARCHAR(255) NOT NULL');
        }

        if (Schema::hasColumn('leads', 'email') && !Schema::hasColumn('leads', 'poc_email')) {
            DB::statement('ALTER TABLE leads CHANGE email poc_email VARCHAR(255) NULL');
        }

        if (Schema::hasColumn('leads', 'phone') && !Schema::hasColumn('leads', 'poc_phone')) {
            DB::statement('ALTER TABLE leads CHANGE phone poc_phone VARCHAR(255) NULL');
        }

        if (!Schema::hasColumn('leads', 'company_website')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('company_website')->nullable()->after('company_name');
            });
        }

        if (!Schema::hasColumn('leads', 'company_linkedin')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('company_linkedin')->nullable()->after('company_website');
            });
        }

        if (!Schema::hasColumn('leads', 'city')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('city')->nullable()->after('company_linkedin');
            });
        }

        if (!Schema::hasColumn('leads', 'country')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('country')->nullable()->after('city');
            });
        }

        if (!Schema::hasColumn('leads', 'poc_linkedin')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('poc_linkedin')->nullable()->after('poc_phone');
            });
        }

        if (!Schema::hasColumn('leads', 'tags')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->text('tags')->nullable()->after('tagline');
            });
        }

        if (!Schema::hasColumn('leads', 'is_converted')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->boolean('is_converted')->default(false)->after('stage');
            });
        }

        if (!Schema::hasColumn('leads', 'converted_at')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->timestamp('converted_at')->nullable()->after('is_converted');
            });
        }

        if (!Schema::hasColumn('leads', 'client_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->after('converted_at');
            });
        }
    }

    public function down(): void
    {
        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('leads', 'company_website') ? 'company_website' : null,
            Schema::hasColumn('leads', 'company_linkedin') ? 'company_linkedin' : null,
            Schema::hasColumn('leads', 'city') ? 'city' : null,
            Schema::hasColumn('leads', 'country') ? 'country' : null,
            Schema::hasColumn('leads', 'poc_linkedin') ? 'poc_linkedin' : null,
            Schema::hasColumn('leads', 'tags') ? 'tags' : null,
            Schema::hasColumn('leads', 'is_converted') ? 'is_converted' : null,
            Schema::hasColumn('leads', 'converted_at') ? 'converted_at' : null,
            Schema::hasColumn('leads', 'client_id') ? 'client_id' : null,
        ]));

        if ($columnsToDrop !== []) {
            Schema::table('leads', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }

        if (Schema::hasColumn('leads', 'poc_name') && !Schema::hasColumn('leads', 'name')) {
            DB::statement('ALTER TABLE leads CHANGE poc_name name VARCHAR(255) NOT NULL');
        }

        if (Schema::hasColumn('leads', 'poc_email') && !Schema::hasColumn('leads', 'email')) {
            DB::statement('ALTER TABLE leads CHANGE poc_email email VARCHAR(255) NULL');
        }

        if (Schema::hasColumn('leads', 'poc_phone') && !Schema::hasColumn('leads', 'phone')) {
            DB::statement('ALTER TABLE leads CHANGE poc_phone phone VARCHAR(255) NULL');
        }
    }
};
