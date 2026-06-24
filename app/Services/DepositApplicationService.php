<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PatientDepositStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\DepositApplication;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PatientDeposit;
use Modules\Billing\Models\Payment;

class DepositApplicationService
{
    public function __construct(
        protected PaymentRecordingService $paymentRecordingService,
        protected InvoiceAllocationBuilder $allocationBuilder,
    ) {}

    /**
     * @param  string[]|null  $lineIds
     */
    public function apply(
        PatientDeposit $deposit,
        Invoice $invoice,
        string $amount,
        ?array $lineIds = null,
        ?int $recordedBy = null,
    ): Payment {
        if (! $deposit->isActive()) {
            throw new InvalidArgumentException('Deposit is not active.');
        }

        if ((string) $deposit->patient_id !== (string) $invoice->patient_id) {
            throw new InvalidArgumentException('Deposit and invoice must belong to the same patient.');
        }

        if ((string) $deposit->branch_id !== (string) $invoice->branch_id) {
            throw new InvalidArgumentException('Deposit and invoice must belong to the same branch.');
        }

        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException('Application amount must be positive.');
        }

        if (bccomp($amount, $deposit->remainingAmount(), 2) > 0) {
            throw new InvalidArgumentException('Application amount exceeds deposit balance.');
        }

        if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)) {
            throw new InvalidArgumentException('Cannot apply deposit to a draft or void invoice.');
        }

        if (bccomp($invoice->balanceDue(), '0', 2) <= 0) {
            throw new InvalidArgumentException('Invoice has no outstanding balance.');
        }

        $maxApply = bccomp($amount, $invoice->balanceDue(), 2) > 0
            ? $invoice->balanceDue()
            : $amount;

        $invoice = $invoice->fresh(['lines']);

        $allocations = $lineIds !== null && $lineIds !== []
            ? $this->allocationBuilder->allocateAmountAcrossUnpaidLines($invoice, $maxApply, $lineIds)
            : $this->allocationBuilder->allocateAmountAcrossUnpaidLines($invoice, $maxApply);

        if ($allocations === []) {
            throw new InvalidArgumentException('No valid allocations for deposit application.');
        }

        $appliedTotal = '0';
        foreach ($allocations as $allocAmount) {
            $appliedTotal = bcadd($appliedTotal, (string) $allocAmount, 2);
        }

        return DB::transaction(function () use ($deposit, $invoice, $appliedTotal, $allocations, $recordedBy) {
            $lockedDeposit = PatientDeposit::query()
                ->whereKey($deposit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedDeposit->isActive()) {
                throw new InvalidArgumentException('Deposit is not active.');
            }

            if (bccomp($appliedTotal, $lockedDeposit->remainingAmount(), 2) > 0) {
                throw new InvalidArgumentException('Application amount exceeds deposit balance.');
            }

            $payment = $this->paymentRecordingService->record(
                allocations: $allocations,
                method: PaymentMethod::Gateway,
                gateway: 'deposit',
                currency: $invoice->currency,
                patientId: $invoice->patient_id,
                branchId: (string) $invoice->branch_id,
                recordedBy: $recordedBy,
                providerTransactionId: (string) Str::uuid(),
                metadata: [
                    'source' => 'deposit_application',
                    'funded_by_deposit_id' => $lockedDeposit->id,
                ],
                type: PaymentType::Payment,
            );

            $newBalance = bcsub($lockedDeposit->remainingAmount(), $appliedTotal, 2);
            $lockedDeposit->update([
                'unallocated_balance' => $newBalance,
                'status' => bccomp($newBalance, '0', 2) <= 0
                    ? PatientDepositStatus::Depleted
                    : PatientDepositStatus::Active,
            ]);

            DepositApplication::query()->create([
                'patient_deposit_id' => $lockedDeposit->id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $appliedTotal,
            ]);

            return $payment->fresh(['allocations']);
        });
    }
}
