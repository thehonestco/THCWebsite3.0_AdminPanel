<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('positions', 'city')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->string('city')->nullable()->after('work_mode');
            });
        }

        if (!Schema::hasColumn('positions', 'country')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->string('country')->nullable()->after('city');
            });
        }
    }

    public function down(): void
    {
        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('positions', 'city') ? 'city' : null,
            Schema::hasColumn('positions', 'country') ? 'country' : null,
        ]));

        if ($columnsToDrop !== []) {
            Schema::table('positions', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
