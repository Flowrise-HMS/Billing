<?php

namespace Modules\Billing\Policies;

use App\Models\User;
use Modules\Billing\Models\Payment;

class PaymentPolicy
{
    protected function sameBranch(User $user, Payment $payment): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $branchId = $user->branch_id;

        return $branchId !== null && (string) $branchId === (string) $payment->branch_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny Payment');
    }

    public function view(User $user, Payment $payment): bool
    {
        if (! $user->can('View Payment')) {
            return false;
        }

        return $this->sameBranch($user, $payment);
    }
}
