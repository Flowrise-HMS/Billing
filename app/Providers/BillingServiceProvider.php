<?php

namespace Modules\Billing\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Observers\InvoiceLineObserver;
use Modules\Billing\Policies\BranchPaymentGatewayConfigPolicy;
use Modules\Billing\Policies\InvoicePolicy;
use Modules\Billing\Policies\PaymentPolicy;
use Modules\Billing\Console\FlagOverdueInvoices;
use Modules\Billing\Services\PatientFinancialHoldService;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Contracts\PatientFinancialHoldChecker;
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
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadViewsFrom(module_path($this->name, 'resources/views'), 'billing');

        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(BranchPaymentGatewayConfig::class, BranchPaymentGatewayConfigPolicy::class);

        InvoiceLine::observe(InvoiceLineObserver::class);

        Encounter::resolveRelationUsing('invoices', function (Encounter $encounter) {
            return $encounter->hasMany(Invoice::class);
        });
    }
}
