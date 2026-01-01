<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('position_id')
                  ->constrained('positions')
                  ->cascadeOnDelete();

            $table->foreignId('applicant_id')
                  ->constrained('applicants')
                  ->cascadeOnDelete();

            // Application-specific candidate data
            $table->decimal('experience_years', 4, 1)->nullable();
            $table->unsignedInteger('current_ctc')->nullable();
            $table->unsignedInteger('expected_ctc')->nullable();
            $table->unsignedInteger('notice_period_days')->nullable();

            // Pipeline
            $table->enum('stage', [
                'open',
                'screening',
                'interview',
                'offer',
                'joined',
                'rejected',
                'on_hold'
            ])->default('open');

            $table->timestamp('last_contact_at')->nullable();
            $table->text('comment')->nullable();

            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamps();

            // Important
            $table->unique(['position_id', 'applicant_id']);
            $table->index(['stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
