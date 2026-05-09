<?php

namespace Modules\Billing\Policies;

use App\Models\User;
use Modules\Billing\Models\Invoice;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft();
    }
}
