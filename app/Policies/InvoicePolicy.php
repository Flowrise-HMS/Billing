<?php

namespace Modules\Billing\Policies;

use App\Models\User;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\Invoice;

class InvoicePolicy
{
    protected function sameBranch(User $user, Invoice $invoice): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $branchId = $user->branch_id;

        return $branchId !== null && (string) $branchId === (string) $invoice->branch_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny Invoice');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $user->can('View Invoice')) {
            return false;
        }

        return $this->sameBranch($user, $invoice);
    }

    public function create(User $user): bool
    {
        return $user->can('Create Invoice');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (! $user->can('Update Invoice')) {
            return false;
        }

        if (! $invoice->isDraft()) {
            return false;
        }

        return $this->sameBranch($user, $invoice);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        if (! $user->can('Delete Invoice')) {
            return false;
        }

        if (! $invoice->isDraft()) {
            return false;
        }

        return $this->sameBranch($user, $invoice);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        if (! $user->can('Update Invoice')) {
            return false;
        }

        if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Void, InvoiceStatus::Paid, InvoiceStatus::PartiallyPaid], true)) {
            return false;
        }

        return $this->sameBranch($user, $invoice);
    }
}
