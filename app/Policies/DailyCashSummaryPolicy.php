<?php

namespace Modules\Billing\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Billing\Models\DailyCashSummary;

class DailyCashSummaryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DailyCashSummary $summary): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DailyCashSummary $summary): bool
    {
        return true;
    }

    public function delete(User $user, DailyCashSummary $summary): bool
    {
        return false;
    }
}
