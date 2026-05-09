<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class BillingCustomPermissionSeeder extends Seeder
{
    /** @var array<string, string[]> permission name => web-guard roles */
    protected array $matrix = [
        'view_invoice_pdf' => ['super_admin', 'billing_clerk', 'medical_biller', 'finance_officer', 'receptionist', 'admissions_staff'],
        'print_invoice' => ['super_admin', 'billing_clerk', 'medical_biller', 'finance_officer', 'receptionist', 'admissions_staff'],
        'download_invoice' => ['super_admin', 'billing_clerk', 'medical_biller', 'finance_officer'],
        'print_receipt' => ['super_admin', 'billing_clerk', 'medical_biller', 'finance_officer', 'receptionist', 'admissions_staff'],
        'download_receipt' => ['super_admin', 'billing_clerk', 'medical_biller', 'finance_officer'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys($this->matrix) as $name) {
            foreach (['web', 'api'] as $guard) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
            }
        }

        foreach ($this->matrix as $name => $roles) {
            $perm = Permission::query()->where(['name' => $name, 'guard_name' => 'web'])->first();
            if (! $perm) {
                continue;
            }

            foreach ($roles as $roleName) {
                Role::query()
                    ->where(['name' => $roleName, 'guard_name' => 'web'])
                    ->first()
                    ?->givePermissionTo($perm);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
