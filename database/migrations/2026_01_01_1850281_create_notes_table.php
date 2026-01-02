<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('user_name');                 // Karthik Nath
            $table->string('title')->nullable();         // Website Redesign
            $table->text('content');
            $table->string('note_status')->default('Pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
