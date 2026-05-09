<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_payment_gateway_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->string('driver', 32);
            $table->string('display_name')->nullable();
            $table->text('public_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('test_mode')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'driver']);
            $table->index(['branch_id', 'is_enabled']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('invoice_number', 40)->unique();
            $table->string('status', 32)->default('draft');
            $table->string('invoice_type', 32)->default('standalone');
            $table->char('currency', 3)->default('GHS');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('encounter_discharged_at')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->unsignedInteger('lock_version')->default(0);
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_email')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'issued_at']);
            $table->index(['patient_id', 'status']);
            $table->index(['encounter_id']);
            $table->index(['appointment_id']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->nullableUuidMorphs('billable');
            $table->foreignUuid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('line_status', 32)->default('unpaid');
            $table->decimal('original_unit_price', 14, 2)->nullable();
            $table->string('adjustment_reason')->nullable();
            $table->decimal('patient_responsibility_amount', 14, 2)->nullable();
            $table->decimal('insurance_expected_amount', 14, 2)->nullable();
            $table->uuid('claim_line_id')->nullable();
            $table->json('payer_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'line_status']);
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 32);
            $table->string('status', 32)->default('pending');
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('GHS');
            $table->json('line_ids')->nullable();
            $table->string('client_reference')->unique();
            $table->string('provider_reference')->nullable()->index();
            $table->text('checkout_url')->nullable();
            $table->json('raw_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->string('method', 32);
            $table->string('gateway', 32)->default('cash');
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('GHS');
            $table->string('provider_transaction_id');
            $table->timestamp('received_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'received_at']);
            $table->index(['branch_id', 'received_at']);
            $table->unique(['gateway', 'provider_transaction_id']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignUuid('invoice_line_id')->constrained('invoice_lines')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_line_id']);
            $table->index('invoice_line_id');
        });

        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('driver', 32);
            $table->string('idempotency_key')->unique();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['driver', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('branch_payment_gateway_configs');
    }
};
