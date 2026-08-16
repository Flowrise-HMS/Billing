<?php

declare(strict_types=1);

namespace Modules\Billing\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Billing\Models\PatientDeposit;
use Illuminate\Auth\Access\HandlesAuthorization;

class PatientDepositPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny PatientDeposit');
    }

    public function view(AuthUser $authUser, PatientDeposit $patientDeposit): bool
    {
        return $authUser->can('View PatientDeposit');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create PatientDeposit');
    }

    public function update(AuthUser $authUser, PatientDeposit $patientDeposit): bool
    {
        return $authUser->can('Update PatientDeposit');
    }

    public function delete(AuthUser $authUser, PatientDeposit $patientDeposit): bool
    {
        return $authUser->can('Delete PatientDeposit');
    }

    public function restore(AuthUser $authUser, PatientDeposit $patientDeposit): bool
    {
        return $authUser->can('Restore PatientDeposit');
    }

    public function forceDelete(AuthUser $authUser, PatientDeposit $patientDeposit): bool
    {
        return $authUser->can('ForceDelete PatientDeposit');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny PatientDeposit');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny PatientDeposit');
    }

    public function replicate(AuthUser $authUser, PatientDeposit $patientDeposit): bool
    {
        return $authUser->can('Replicate PatientDeposit');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder PatientDeposit');
    }

}