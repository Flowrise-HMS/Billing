<?php

namespace Modules\Billing\Policies;

use App\Models\User;
use Modules\Billing\Models\Payment;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payment $payment): bool
    {
        return true;
    }
}
