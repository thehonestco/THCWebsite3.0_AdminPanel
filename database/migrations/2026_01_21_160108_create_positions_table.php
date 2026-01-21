<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();

            // Core
            $table->string('position_name'); // UI/UX Designer
            $table->foreignId('job_description_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Job attributes
            $table->enum('job_type', ['Full Time', 'Part Time', 'Contract']);
            $table->enum('work_mode', ['Onsite', 'Remote', 'Hybrid']);

            // Ranges
            $table->unsignedTinyInteger('experience_min')->nullable();
            $table->unsignedTinyInteger('experience_max')->nullable();

            $table->unsignedInteger('salary_min')->nullable(); // LPA
            $table->unsignedInteger('salary_max')->nullable(); // LPA

            // Position lifecycle
            $table->enum('status', ['open', 'closed'])->default('open');

            // Audit
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status']);
            $table->index(['job_type', 'work_mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
