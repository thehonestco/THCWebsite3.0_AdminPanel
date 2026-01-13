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
        // STEP 1: Rename existing columns FIRST
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('name', 'poc_name');
            $table->renameColumn('email', 'poc_email');
            $table->renameColumn('phone', 'poc_phone');
        });

        // STEP 2: Add new columns
        Schema::table('leads', function (Blueprint $table) {

            // Company info
            $table->string('company_website')->nullable()->after('company_name');
            $table->string('company_linkedin')->nullable()->after('company_website');

            // Location
            $table->string('city')->nullable()->after('company_linkedin');
            $table->string('country')->nullable()->after('city');

            // POC extra
            $table->string('poc_linkedin')->nullable()->after('poc_phone');

            // Tags
            $table->text('tags')->nullable()->after('tagline');

            // Lead → Client conversion
            $table->boolean('is_converted')->default(false)->after('stage');
            $table->timestamp('converted_at')->nullable()->after('is_converted');
            $table->unsignedBigInteger('client_id')->nullable()->after('converted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'company_website',
                'company_linkedin',
                'city',
                'country',
                'poc_linkedin',
                'tags',
                'is_converted',
                'converted_at',
                'client_id',
            ]);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('poc_name', 'name');
            $table->renameColumn('poc_email', 'email');
            $table->renameColumn('poc_phone', 'phone');
        });
    }
};
