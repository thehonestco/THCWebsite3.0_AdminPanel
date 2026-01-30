<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->string('city')->nullable()->after('work_mode');
            $table->string('country')->nullable()->after('city');
        });
    }

    public function down()
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn(['city', 'country']);
        });
    }
};
