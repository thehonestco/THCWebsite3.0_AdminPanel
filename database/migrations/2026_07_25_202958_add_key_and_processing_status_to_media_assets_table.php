<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->string('key')->nullable()->after('path');
        });

        DB::table('media_assets')->whereNull('key')->update(['key' => DB::raw('path')]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE media_assets MODIFY processing_status ENUM('processing', 'ready', 'failed') NOT NULL DEFAULT 'ready'");
            DB::statement('ALTER TABLE media_assets MODIFY path VARCHAR(255) NULL');
            DB::statement('ALTER TABLE media_assets MODIFY converted_extension VARCHAR(20) NULL');
            DB::statement('ALTER TABLE media_assets MODIFY converted_mime_type VARCHAR(255) NULL');
            DB::statement('ALTER TABLE media_assets MODIFY url TEXT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE media_assets SET processing_status = 'ready' WHERE processing_status = 'processing'");
            DB::statement("ALTER TABLE media_assets MODIFY processing_status ENUM('ready', 'failed') NOT NULL DEFAULT 'ready'");
            DB::statement('ALTER TABLE media_assets MODIFY path VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE media_assets MODIFY converted_extension VARCHAR(20) NOT NULL');
            DB::statement('ALTER TABLE media_assets MODIFY converted_mime_type VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE media_assets MODIFY url TEXT NOT NULL');
        }

        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropColumn('key');
        });
    }
};
