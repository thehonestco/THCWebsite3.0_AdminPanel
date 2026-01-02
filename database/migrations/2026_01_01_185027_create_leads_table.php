<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_code')->unique();        // L009
            $table->string('company_name');               // Sarvasva Capital
            $table->string('tagline')->nullable();        // Gold loans...
            $table->string('name');                       // Rajesh Matta
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();         // Email Marketing
            $table->string('stage')->default('Requirement');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
