<?php

namespace Modules\Billing\Filament\Clusters\Billing\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PatientDeposit;
use Modules\Core\Models\Branch;

class DepositsAndOutstanding extends Page
{
    use HasPageShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected string $view = 'billing::filament.clusters.billing.pages.deposits-and-outstanding';

    public ?string $branchId = null;

    public function mount(): void
    {
        $this->branchId = request()->query('branch_id', Auth::user()?->branch_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDepositBalances(): array
    {
        return PatientDeposit::query()
            ->when($this->branchId !== null, fn ($query) => $query->where('branch_id', $this->branchId))
            ->with(['patient' => fn ($query) => $query->withoutGlobalScopes()])
            ->get()
            ->groupBy('patient_id')
            ->map(function ($deposits): array {
                $deposited = '0';
                $remaining = '0';
                $applied = '0';

                foreach ($deposits as $deposit) {
                    $deposited = bcadd($deposited, (string) $deposit->amount, 2);
                    $remaining = bcadd($remaining, (string) $deposit->unallocated_balance, 2);
                    $applied = bcadd(
                        $applied,
                        bcsub((string) $deposit->amount, (string) $deposit->unallocated_balance, 2),
                        2
                    );
                }

                return [
                    'patient_id' => (string) $deposits->first()->patient_id,
                    'patient_name' => $deposits->first()->patient?->full_name ?? __('N/A'),
                    'mrn' => $deposits->first()->patient?->mrn ?? '',
                    'deposited' => $deposited,
                    'applied' => $applied,
                    'remaining' => $remaining,
                    'currency' => (string) ($deposits->first()->currency ?? 'GHS'),
                ];
            })
            ->values()
            ->sortByDesc('remaining')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOutstanding(): array
    {
        $query = Invoice::query()
            ->withoutGlobalScopes()
            ->with([
                'patient' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void]);

        if ($this->branchId !== null) {
            $query->where('branch_id', $this->branchId);
        }

        return $query
            ->get()
            ->filter(fn (Invoice $invoice) => bccomp(
                bcsub((string) $invoice->total, (string) $invoice->amount_paid, 2),
                '0',
                2
            ) > 0)
            ->groupBy('patient_id')
            ->map(function ($invoices): array {
                $total = '0';
                foreach ($invoices as $invoice) {
                    $total = bcadd($total, bcsub((string) $invoice->total, (string) $invoice->amount_paid, 2), 2);
                }

                $first = $invoices->first();

                return [
                    'patient_id' => (string) $first->patient_id,
                    'patient_name' => $first->patient?->full_name ?? __('N/A'),
                    'mrn' => $first->patient?->mrn ?? '',
                    'outstanding' => $total,
                    'currency' => (string) ($first->currency ?? 'GHS'),
                    'invoice_count' => $invoices->count(),
                ];
            })
            ->values()
            ->sortByDesc('outstanding')
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function getBranchesProperty(): array
    {
        return Branch::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
