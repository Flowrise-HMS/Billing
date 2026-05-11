<?php

namespace Modules\Billing\Policies;

use App\Models\User;
use Modules\Billing\Models\BranchPaymentGatewayConfig;

class BranchPaymentGatewayConfigPolicy
{
    protected function sameBranch(User $user, BranchPaymentGatewayConfig $config): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $branchId = $user->branch_id;

        return $branchId !== null && (string) $branchId === (string) $config->branch_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny BranchPaymentGatewayConfig');
    }

    public function view(User $user, BranchPaymentGatewayConfig $branchPaymentGatewayConfig): bool
    {
        if (! $user->can('View BranchPaymentGatewayConfig')) {
            return false;
        }

        return $this->sameBranch($user, $branchPaymentGatewayConfig);
    }

    public function create(User $user): bool
    {
        return $user->can('Create BranchPaymentGatewayConfig');
    }

    public function update(User $user, BranchPaymentGatewayConfig $branchPaymentGatewayConfig): bool
    {
        if (! $user->can('Update BranchPaymentGatewayConfig')) {
            return false;
        }

        return $this->sameBranch($user, $branchPaymentGatewayConfig);
    }

    public function delete(User $user, BranchPaymentGatewayConfig $branchPaymentGatewayConfig): bool
    {
        if (! $user->can('Delete BranchPaymentGatewayConfig')) {
            return false;
        }

        return $this->sameBranch($user, $branchPaymentGatewayConfig);
    }
}
