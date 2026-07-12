<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('title')->nullable();
            $table->enum('media_type', ['image', 'video']);
            $table->string('disk', 50);
            $table->string('directory');
            $table->string('file_name');
            $table->string('path')->unique();
            $table->text('url');
            $table->string('source_extension', 20)->nullable();
            $table->string('source_mime_type')->nullable();
            $table->string('converted_extension', 20);
            $table->string('converted_mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedDecimal('duration_seconds', 8, 2)->nullable();
            $table->enum('processing_status', ['ready', 'failed'])->default('ready');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['media_type', 'created_at']);
            $table->index(['created_by', 'created_at']);
            $table->index('converted_extension');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
