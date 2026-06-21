<?php

namespace Modules\Billing\Database\Seeders;

use Database\Seeders\ShieldSeeder;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class BillingShieldPermissionsSeeder extends Seeder
{
    /**
     * Grant Filament Shield permissions for Billing resources to operational roles.
     * Run after {@see ShieldSeeder} so permissions exist.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $billingFinanceNames = Permission::query()
            ->where('guard_name', $guard)
            ->where(function ($q): void {
                $q->where('name', 'View BillingCluster')
                    ->orWhere('name', 'like', '% Invoice')
                    ->orWhere('name', 'like', '% Payment')
                    ->orWhere('name', 'like', '%BranchPaymentGatewayConfig')
                    ->orWhere('name', 'like', '%RevenueReport%')
                    ->orWhere('name', 'like', '%ChartWidget%');
            })
            ->pluck('name')
            ->all();

        $frontDeskInvoiceNames = [
            'View BillingCluster',
            'ViewAny Invoice',
            'View Invoice',
            'Create Invoice',
            'Update Invoice',
        ];

        $this->giveNamedPermissionsToRole('billing_clerk', $billingFinanceNames, $guard);
        $this->giveNamedPermissionsToRole('receptionist', $frontDeskInvoiceNames, $guard);
        $this->giveNamedPermissionsToRole('admissions_staff', $frontDeskInvoiceNames, $guard);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $names
     */
    protected function giveNamedPermissionsToRole(string $roleName, array $names, string $guard): void
    {
        $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();
        if ($role === null) {
            return;
        }

        $existing = Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        if ($existing === []) {
            return;
        }

        $role->givePermissionTo($existing);
    }
}
