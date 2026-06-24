<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->decimal('total_amount', 14, 2);
            $table->decimal('down_payment', 14, 2)->default(0);
            $table->integer('installment_count');
            $table->integer('frequency_days')->default(30);
            $table->string('status', 32)->default('active');
            $table->date('start_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_plan_installments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_plan_id')->constrained('payment_plans')->cascadeOnDelete();
            $table->integer('installment_number');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('status', 32)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['payment_plan_id', 'installment_number'], 'plan_installment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plan_installments');
        Schema::dropIfExists('payment_plans');
    }
};
