<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentPlanInstallmentStatus;
use Modules\Billing\Enums\PaymentPlanStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentPlan;
use Modules\Billing\Models\PaymentPlanInstallment;
use RuntimeException;

class PaymentPlanService
{
    public function __construct(
        protected PaymentRecordingService $paymentRecordingService,
        protected InvoiceAllocationBuilder $allocationBuilder,
    ) {}

    public function createPlan(
        Invoice $invoice,
        int $installmentCount,
        int $frequencyDays = 30,
        string $downPayment = '0',
        ?string $notes = null,
    ): PaymentPlan {
        if (! in_array($invoice->status->value, ['issued', 'partially_paid'])) {
            throw new RuntimeException('Payment plans can only be created on issued or partially paid invoices.');
        }

        $balance = $invoice->balanceDue();
        if (bccomp($balance, '0', 2) <= 0) {
            throw new RuntimeException('Invoice has no outstanding balance.');
        }

        if ($invoice->paymentPlan()->where('status', PaymentPlanStatus::Active)->exists()) {
            throw new RuntimeException('Invoice already has an active payment plan.');
        }

        if (bccomp($downPayment, $balance, 2) > 0) {
            throw new RuntimeException('Down payment cannot exceed invoice balance.');
        }

        return DB::transaction(function () use ($invoice, $installmentCount, $frequencyDays, $downPayment, $notes, $balance) {
            $plan = PaymentPlan::query()->create([
                'invoice_id' => $invoice->id,
                'total_amount' => $balance,
                'down_payment' => $downPayment,
                'installment_count' => $installmentCount,
                'frequency_days' => $frequencyDays,
                'status' => PaymentPlanStatus::Active,
                'start_date' => now()->startOfDay(),
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);

            $this->generateSchedule($plan);

            return $plan->fresh(['installments']);
        });
    }

    public function generateSchedule(PaymentPlan $plan): void
    {
        $installments = [];
        $remaining = bcsub((string) $plan->total_amount, (string) $plan->down_payment, 2);
        $startDate = $plan->start_date->copy();
        $number = 0;

        if (bccomp((string) $plan->down_payment, '0', 2) > 0) {
            $number++;
            $installments[] = [
                'payment_plan_id' => $plan->id,
                'installment_number' => $number,
                'due_date' => $startDate,
                'amount' => $plan->down_payment,
                'paid_amount' => '0',
                'status' => PaymentPlanInstallmentStatus::Pending,
            ];
        }

        $installmentAmount = bcdiv($remaining, (string) $plan->installment_count, 2);
        $accumulated = '0';

        for ($i = 1; $i <= $plan->installment_count; $i++) {
            $number++;
            $dueDate = (clone $startDate)->addDays($i * $plan->frequency_days);

            if ($i === $plan->installment_count) {
                $amount = bcsub($remaining, $accumulated, 2);
            } else {
                $amount = $installmentAmount;
            }

            $accumulated = bcadd($accumulated, $amount, 2);

            $installments[] = [
                'payment_plan_id' => $plan->id,
                'installment_number' => $number,
                'due_date' => $dueDate,
                'amount' => $amount,
                'paid_amount' => '0',
                'status' => PaymentPlanInstallmentStatus::Pending,
            ];
        }

        foreach ($installments as $installment) {
            PaymentPlanInstallment::query()->create($installment);
        }
    }

    public function recordInstallmentPayment(
        PaymentPlan $plan,
        PaymentPlanInstallment $installment,
        string $amount,
        PaymentMethod $method,
        string $gateway,
        ?string $reference = null,
    ): void {
        if ($plan->status !== PaymentPlanStatus::Active) {
            throw new RuntimeException('Cannot collect payment on a non-active payment plan.');
        }

        $invoice = $plan->invoice;

        $remaining = $installment->remainingAmount();
        if (bccomp($amount, '0', 2) <= 0) {
            throw new RuntimeException('Payment amount must be positive.');
        }

        $collectAmount = bccomp($amount, $remaining, 2) > 0 ? $remaining : $amount;

        $allocations = $this->allocationBuilder->allocateAmountAcrossUnpaidLines(
            $invoice,
            $collectAmount,
        );

        if (empty($allocations)) {
            throw new RuntimeException('Invoice has no unpaid lines available for allocation.');
        }

        $metadata = [
            'source' => 'payment_plan',
            'payment_plan_id' => $plan->id,
            'installment_number' => $installment->installment_number,
            'reference' => $reference ?? '',
        ];

        DB::transaction(function () use ($allocations, $method, $gateway, $invoice, $metadata, $plan, $installment, $collectAmount) {
            $this->paymentRecordingService->record(
                allocations: $allocations,
                method: $method,
                gateway: $gateway,
                currency: $invoice->currency,
                patientId: $invoice->patient_id,
                branchId: (string) $invoice->branch_id,
                recordedBy: Auth::id(),
                metadata: $metadata,
            );

            $newPaid = bcadd((string) $installment->paid_amount, $collectAmount, 2);
            $installmentStatus = bccomp($newPaid, (string) $installment->amount, 2) >= 0
                ? PaymentPlanInstallmentStatus::Paid
                : PaymentPlanInstallmentStatus::Partial;

            $installment->update([
                'paid_amount' => $newPaid,
                'status' => $installmentStatus,
                'paid_at' => $installmentStatus === PaymentPlanInstallmentStatus::Paid ? now() : null,
            ]);

            $this->syncPlanStatus($plan);
        });
    }

    public function syncPlanStatus(PaymentPlan $plan): void
    {
        $plan->refresh(['installments']);

        $allPaid = $plan->installments->every(fn ($i) => $i->isFullyPaid());

        if ($allPaid) {
            $plan->update(['status' => PaymentPlanStatus::Completed]);
        }
    }

    public function cancelPlan(PaymentPlan $plan): void
    {
        $plan->update(['status' => PaymentPlanStatus::Cancelled]);
    }
}
