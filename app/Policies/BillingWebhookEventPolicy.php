<?php

declare(strict_types=1);

namespace Modules\Billing\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Billing\Models\BillingWebhookEvent;

class BillingWebhookEventPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny BillingWebhookEvent');
    }

    public function view(AuthUser $authUser, BillingWebhookEvent $billingWebhookEvent): bool
    {
        return $authUser->can('View BillingWebhookEvent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create BillingWebhookEvent');
    }

    public function update(AuthUser $authUser, BillingWebhookEvent $billingWebhookEvent): bool
    {
        return $authUser->can('Update BillingWebhookEvent');
    }

    public function delete(AuthUser $authUser, BillingWebhookEvent $billingWebhookEvent): bool
    {
        return $authUser->can('Delete BillingWebhookEvent');
    }

    public function restore(AuthUser $authUser, BillingWebhookEvent $billingWebhookEvent): bool
    {
        return $authUser->can('Restore BillingWebhookEvent');
    }

    public function forceDelete(AuthUser $authUser, BillingWebhookEvent $billingWebhookEvent): bool
    {
        return $authUser->can('ForceDelete BillingWebhookEvent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny BillingWebhookEvent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny BillingWebhookEvent');
    }

    public function replicate(AuthUser $authUser, BillingWebhookEvent $billingWebhookEvent): bool
    {
        return $authUser->can('Replicate BillingWebhookEvent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder BillingWebhookEvent');
    }
}
