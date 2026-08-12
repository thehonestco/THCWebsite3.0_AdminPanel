<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->index(
                ['deleted_at', 'resource_type', 'status', 'updated_at', 'id'],
                'resources_filtered_listing_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropIndex('resources_filtered_listing_index');
        });
    }
};
