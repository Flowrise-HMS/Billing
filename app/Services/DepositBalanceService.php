<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Collection;
use Modules\Billing\Enums\PatientDepositStatus;
use Modules\Billing\Models\PatientDeposit;

class DepositBalanceService
{
    public function unallocatedBalanceForPatient(string $patientId, ?string $branchId = null): string
    {
        $query = PatientDeposit::query()
            ->where('patient_id', $patientId)
            ->where('status', PatientDepositStatus::Active);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $sum = '0';
        foreach ($query->pluck('unallocated_balance') as $balance) {
            $sum = bcadd($sum, (string) $balance, 2);
        }

        return $sum;
    }

    /**
     * @return Collection<int, PatientDeposit>
     */
    public function activeDepositsForPatient(string $patientId, ?string $branchId = null): Collection
    {
        $query = PatientDeposit::query()
            ->where('patient_id', $patientId)
            ->where('status', PatientDepositStatus::Active)
            ->where('unallocated_balance', '>', 0)
            ->orderBy('created_at');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }
}
