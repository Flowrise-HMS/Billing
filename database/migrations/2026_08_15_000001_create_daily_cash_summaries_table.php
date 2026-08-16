<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_cash_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->date('summary_date');
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->decimal('change_given', 14, 2)->default(0);
            $table->decimal('counted_closing', 14, 2)->nullable();
            $table->decimal('expected_closing', 14, 2)->default(0);
            $table->decimal('variance', 14, 2)->default(0);
            $table->string('status', 16)->default('open');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'cashier_id', 'summary_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['received_at', 'branch_id', 'type', 'method'], 'payments_closeout_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_closeout_idx');
        });

        Schema::dropIfExists('daily_cash_summaries');
    }
};
