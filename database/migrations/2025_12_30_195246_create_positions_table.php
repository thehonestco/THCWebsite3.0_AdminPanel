<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('job_description_id')
                  ->constrained('job_descriptions')
                  ->cascadeOnDelete();

            // Position Information
            $table->string('organization_name');
            $table->enum('source', ['Referral', 'Inbound', 'Outbound']);
            $table->string('location')->nullable();
            $table->string('website_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->text('tags')->nullable();

            // Position Details (Contact)
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            $table->enum('status', ['active', 'closed'])->default('active');

            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
