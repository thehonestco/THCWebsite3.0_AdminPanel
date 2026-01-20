<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Invoice identity
            $table->string('invoice_no')->unique();
            $table->date('invoice_date');

            // Invoice type
            $table->enum('type', [
                'export',
                'proforma',
                'up',
                'non_up'
            ]);

            // Relations
            $table->foreignId('lead_id')
                ->constrained('leads')
                ->cascadeOnDelete();

            $table->foreignId('bank_detail_id')
                ->constrained('bank_details')
                ->restrictOnDelete();

            // Description & Status (FIX)
            $table->string('description')->nullable();
            $table->enum('status', [
                'draft',
                'pending',
                'paid',
                'overdue'
            ])->default('draft');

            // Totals
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('cgst', 12, 2)->default(0);
            $table->decimal('sgst', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
