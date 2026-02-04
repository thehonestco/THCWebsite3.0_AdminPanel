<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {

            // ➕ new columns
            $table->text('comment')->after('opportunity_id');
            $table->string('created_by')->nullable()->after('comment');

            // ➖ remove old columns
            $table->dropColumn([
                'user_name',
                'title',
                'content',
                'note_status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {

            // ➕ restore old columns
            $table->string('user_name');
            $table->string('title')->nullable();
            $table->text('content');
            $table->string('note_status')->default('Pending');

            // ➖ remove new columns
            $table->dropColumn([
                'comment',
                'created_by',
            ]);
        });
    }
};
