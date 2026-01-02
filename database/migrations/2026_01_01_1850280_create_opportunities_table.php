<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('title');                      // Beta Lending
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('owner_name')->nullable();     // Anita Rao
            $table->string('stage')->default('Requirement');
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
