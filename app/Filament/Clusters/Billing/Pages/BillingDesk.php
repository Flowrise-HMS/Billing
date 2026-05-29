<?php

namespace Modules\Billing\Filament\Clusters\Billing\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Filament\Actions\RecordInvoicePaymentAction;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Models\Invoice;

class BillingDesk extends Page
{
    use HasPageShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected string $view = 'billing::filament.clusters.billing.pages.billing-desk';

    public string $search = '';

    public string $statusFilter = 'unpaid';

    public ?string $sourceFilter = null;

    public ?string $selectedInvoiceId = null;

    public array $invoices = [];

    public ?array $selectedInvoice = null;

    public function mount(): void
    {
        $this->loadInvoices();
    }

    public function updatedSearch(): void
    {
        $this->loadInvoices();
    }

    public function updatedStatusFilter(): void
    {
        $this->loadInvoices();
    }

    public function updatedSourceFilter(): void
    {
        $this->loadInvoices();
    }

    public function selectInvoice(string $invoiceId): void
    {
        $this->selectedInvoiceId = $invoiceId;
        $this->loadSelectedInvoice();
    }

    protected function loadSelectedInvoice(): void
    {
        if (! $this->selectedInvoiceId) {
            $this->selectedInvoice = null;
            return;
        }

        $invoice = Invoice::with(['lines' => fn ($q) => $q->orderBy('id'), 'patient'])
            ->withoutGlobalScopes()
            ->find($this->selectedInvoiceId);

        if (! $invoice) {
            $this->selectedInvoice = null;
            return;
        }

        $this->selectedInvoice = [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->getLabel(),
            'total' => $invoice->total,
            'amount_paid' => $invoice->amount_paid,
            'balance_due' => $invoice->balanceDue(),
            'currency' => $invoice->currency ?? 'GHS',
            'patient_name' => $invoice->patient?->display_name ?? $invoice->guest_name ?? __('Walk-in'),
            'issued_at' => $invoice->issued_at?->format('Y-m-d H:i'),
            'source' => data_get($invoice->metadata, 'source'),
            'lines' => $invoice->lines
                ->reject(fn ($l) => $l->line_status === InvoiceLineStatus::Void)
                ->values()
                ->toArray(),
        ];
    }

    public function loadInvoices(): void
    {
        $query = Invoice::query()
            ->withoutGlobalScopes()
            ->with('patient')
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid]);

        $branchId = Context::get('current_branch_id', Auth::user()?->branch_id);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($this->sourceFilter === 'pharmacy') {
            $query->where('metadata->source', 'pharmacy_pos');
        } elseif ($this->sourceFilter === 'encounter') {
            $query->whereNotNull('encounter_id');
        }

        if ($this->statusFilter === 'unpaid') {
            $query->whereRaw('CAST(total AS DECIMAL(14,2)) > CAST(amount_paid AS DECIMAL(14,2))');
        }

        if ($this->search) {
            $search = $this->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%")
                    ->orWhere('guest_phone', 'like', "%{$search}%")
                    ->orWhereHas('patient', function (Builder $pq) use ($search) {
                        $pq->where('mrn', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $this->invoices = $query->orderBy('issued_at', 'asc')
            ->limit(50)
            ->get()
            ->map(fn (Invoice $inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'patient_name' => $inv->patient?->display_name ?? $inv->guest_name ?? __('Walk-in'),
                'status' => $inv->status->value,
                'status_label' => $inv->status->getLabel(),
                'total' => $inv->total,
                'balance_due' => $inv->balanceDue(),
                'issued_at' => $inv->issued_at?->format('Y-m-d H:i'),
            ])
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            RecordInvoicePaymentAction::make()
                ->arguments(fn (): array => ['invoice_id' => $this->selectedInvoiceId ?? ''])
                ->visible(fn (): bool => $this->selectedInvoiceId !== null)
                ->label(__('Collect payment')),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Billing Desk');
    }
}
