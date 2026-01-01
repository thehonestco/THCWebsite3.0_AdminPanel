<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_descriptions', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->enum('status', ['draft', 'active', 'closed'])
                  ->default('draft');

            $table->longText('about_job')->nullable();
            $table->longText('key_skills')->nullable();
            $table->longText('responsibilities')->nullable();

            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_descriptions');
    }
};
