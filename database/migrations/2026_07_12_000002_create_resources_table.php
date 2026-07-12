<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 100);
            $table->string('sub_industry', 100)->nullable();
            $table->string('sub_service', 100)->nullable();
            $table->string('listing_title');
            $table->text('listing_description')->nullable();
            $table->text('listing_image_url')->nullable();
            $table->foreignId('listing_image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->json('resource_payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['resource_type', 'status']);
            $table->index(['updated_by', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
