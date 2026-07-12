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
            $table->string('media_code', 30)->nullable()->after('id');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('media_type');
        });

        DB::table('media_assets')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($asset) {
                DB::table('media_assets')
                    ->where('id', $asset->id)
                    ->update([
                        'media_code' => 'MC-' . str_pad((string) $asset->id, 3, '0', STR_PAD_LEFT),
                    ]);
            });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE media_assets MODIFY media_type ENUM('image', 'video', 'pdf', 'file') NOT NULL");
        }

        Schema::table('media_assets', function (Blueprint $table) {
            $table->unique('media_code');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropIndex('media_assets_status_created_at_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE media_assets MODIFY media_type ENUM('image', 'video') NOT NULL");
        }

        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropUnique(['media_code']);
            $table->dropColumn(['media_code', 'status']);
        });
    }
};
