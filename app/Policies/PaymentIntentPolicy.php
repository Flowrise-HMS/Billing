<?php

declare(strict_types=1);

namespace Modules\Billing\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Billing\Models\PaymentIntent;

class PaymentIntentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny PaymentIntent');
    }

    public function view(AuthUser $authUser, PaymentIntent $paymentIntent): bool
    {
        return $authUser->can('View PaymentIntent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create PaymentIntent');
    }

    public function update(AuthUser $authUser, PaymentIntent $paymentIntent): bool
    {
        return $authUser->can('Update PaymentIntent');
    }

    public function delete(AuthUser $authUser, PaymentIntent $paymentIntent): bool
    {
        return $authUser->can('Delete PaymentIntent');
    }

    public function restore(AuthUser $authUser, PaymentIntent $paymentIntent): bool
    {
        return $authUser->can('Restore PaymentIntent');
    }

    public function forceDelete(AuthUser $authUser, PaymentIntent $paymentIntent): bool
    {
        return $authUser->can('ForceDelete PaymentIntent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny PaymentIntent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny PaymentIntent');
    }

    public function replicate(AuthUser $authUser, PaymentIntent $paymentIntent): bool
    {
        return $authUser->can('Replicate PaymentIntent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder PaymentIntent');
    }
}
