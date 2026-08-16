<?php

namespace Modules\Billing\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Console\FlagOverdueInvoices;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\PatientDeposit;
use Modules\Billing\Models\Payment;
use Modules\Billing\Observers\InvoiceLineObserver;
use Modules\Billing\Policies\BranchPaymentGatewayConfigPolicy;
use Modules\Billing\Policies\InvoicePolicy;
use Modules\Billing\Policies\PaymentPolicy;
use Modules\Billing\Services\EncounterInvoiceService;
use Modules\Billing\Services\InvoiceLineSyncService;
use Modules\Billing\Services\PatientFinancialHoldService;
use Modules\Core\Contracts\EncounterInvoiceContract;
use Modules\Core\Contracts\InvoiceLineSyncContract;
use Modules\Core\Contracts\PatientFinancialHoldChecker;
use Modules\Core\Support\OptionalClass;
use Nwidart\Modules\Support\ModuleServiceProvider;

class BillingServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Billing';

    protected string $nameLower = 'billing';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    protected array $commands = [
        FlagOverdueInvoices::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(PatientFinancialHoldChecker::class, PatientFinancialHoldService::class);
        $this->app->bind(EncounterInvoiceContract::class, EncounterInvoiceService::class);
        $this->app->bind(InvoiceLineSyncContract::class, InvoiceLineSyncService::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadViewsFrom(module_path($this->name, 'resources/views'), 'billing');

        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(BranchPaymentGatewayConfig::class, BranchPaymentGatewayConfigPolicy::class);

        InvoiceLine::observe(InvoiceLineObserver::class);
        $this->registerCommandSchedules();
        OptionalClass::when(
            'Modules\\Clinical\\Models\\Encounter',
            function (string $encounterClass): void {
                $encounterClass::resolveRelationUsing('invoices', function ($encounter) {
                    return $encounter->hasMany(Invoice::class, 'encounter_id', 'id');
                });
            },
            'Clinical',
        );

        OptionalClass::when(
            'Modules\\Patient\\Models\\Patient',
            function (string $patientClass): void {
                $patientClass::resolveRelationUsing('invoices', function ($patient) {
                    return $patient->hasMany(Invoice::class, 'patient_id', 'id');
                });
                $patientClass::resolveRelationUsing('payments', function ($patient) {
                    return $patient->hasMany(Payment::class, 'patient_id', 'id');
                });
                $patientClass::resolveRelationUsing('deposits', function ($patient) {
                    return $patient->hasMany(PatientDeposit::class, 'patient_id', 'id');
                });
            },
            'Patient',
        );

        OptionalClass::when(
            'Modules\\Clinical\\Models\\RequestItem',
            function (string $requestItemClass): void {
                $requestItemClass::resolveRelationUsing('invoiceLine', function ($item) {
                    return $item->morphOne(InvoiceLine::class, 'billable', 'billable_type', 'billable_id', 'id');
                });
            },
            'Clinical',
        );
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('invoices:check-overdue')->dailyAt('08:00');
        });
    }
}
