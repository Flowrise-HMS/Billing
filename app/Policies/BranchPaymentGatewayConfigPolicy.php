<?php

namespace Modules\Billing\Policies;

use App\Models\User;
use Modules\Billing\Models\BranchPaymentGatewayConfig;

class BranchPaymentGatewayConfigPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BranchPaymentGatewayConfig $branchPaymentGatewayConfig): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BranchPaymentGatewayConfig $branchPaymentGatewayConfig): bool
    {
        return true;
    }

    public function delete(User $user, BranchPaymentGatewayConfig $branchPaymentGatewayConfig): bool
    {
        return true;
    }
}
