<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Billing\Enums\PatientDepositStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\PatientDeposit;
use Modules\Billing\Models\Payment;

class DepositRecordingService
{
    public function record(
        string $patientId,
        string $branchId,
        string $amount,
        PaymentMethod|string $method,
        ?string $reference = null,
        ?int $recordedBy = null,
        string $currency = 'GHS',
    ): PatientDeposit {
        $methodValue = $method instanceof PaymentMethod ? $method->value : (string) $method;

        return DB::transaction(function () use ($patientId, $branchId, $amount, $method, $methodValue, $reference, $recordedBy, $currency) {
            $payment = Payment::query()->create([
                'patient_id' => $patientId,
                'branch_id' => $branchId,
                'method' => $method,
                'gateway' => $methodValue,
                'type' => PaymentType::Deposit,
                'amount' => $amount,
                'currency' => $currency,
                'provider_transaction_id' => $reference ?? (string) Str::uuid(),
                'received_at' => now(),
                'recorded_by' => $recordedBy,
                'metadata' => ['source' => 'deposit_recording_service'],
            ]);

            return PatientDeposit::query()->create([
                'patient_id' => $patientId,
                'branch_id' => $branchId,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'unallocated_balance' => $amount,
                'currency' => $currency,
                'status' => PatientDepositStatus::Active,
                'recorded_by' => $recordedBy,
            ]);
        });
    }
}
