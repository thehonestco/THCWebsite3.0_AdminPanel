<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->index(['deleted_at', 'updated_at'], 'resources_deleted_at_updated_at_index');
            $table->index(['deleted_at', 'status', 'updated_at'], 'resources_deleted_at_status_updated_at_index');
            $table->index(['deleted_at', 'resource_type', 'updated_at'], 'resources_deleted_at_resource_type_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropIndex('resources_deleted_at_updated_at_index');
            $table->dropIndex('resources_deleted_at_status_updated_at_index');
            $table->dropIndex('resources_deleted_at_resource_type_updated_at_index');
        });
    }
};
