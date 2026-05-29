<?php

namespace Modules\Billing\Filament\Clusters\Billing\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Filament\Actions\RecordInvoicePaymentAction;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Tables\InvoicesTable;
use Modules\Billing\Models\Invoice;
use Modules\Core\Classes\Services\BranchService;

class BillingDesk extends Page implements HasTable
{
    use HasPageShield, InteractsWithTable;

    // protected static ?string $cluster = Billing  Cluster::class;

    protected static ?int $navigationSort = -3;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected string $view = 'billing::filament.clusters.billing.pages.billing-desk';

    public ?string $selectedInvoiceId = null;

    public ?array $selectedInvoice = null;

    public function selectInvoice(string $recordKey): void
    {
        $this->selectedInvoiceId = $recordKey;
        $this->loadSelectedInvoice();
    }

    protected function loadSelectedInvoice(): void
    {
        if (! $this->selectedInvoiceId) {
            $this->selectedInvoice = null;
            return;
        }

        $invoice = Invoice::with(['lines' => fn ($q) => $q->orderBy('id')->with('paymentAllocations.payment'), 'patient'])
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
                ->map(fn ($l) => array_merge($l->toArray(), [
                    'latest_payment_id' => $l->paymentAllocations
                        ->sortByDesc(fn ($pa) => $pa->payment?->created_at)
                        ->first()?->payment_id,
                ]))
                ->values()
                ->toArray(),
        ];
    }



    public function table(Table $table): Table
    {
        return InvoicesTable::configure($table)
            ->query(fn (): Builder => Invoice::with('patient')
                ->when($branchId = app(BranchService::class)->getDefaultBranchId(), fn (Builder $q) => $q->where('branch_id', $branchId))
            )
            ->recordAction('selectInvoice')
            ->recordActions([
                Action::make('invoice_pdf')
                    ->label(__('Print'))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn ($record) => route('billing.invoices.pdf', $record))
                    ->openUrlInNewTab()
                    ->visible(fn () => (Auth::user()?->can('view_invoice_pdf') || Auth::user()?->can('print_invoice'))),
                RecordInvoicePaymentAction::make()
                    ->mountUsing(function ($action, $record): void {
                        $action->arguments(['invoice_id' => $record?->id]);
                    })
                    ->visible(fn ($record): bool => ! in_array($record?->status, [InvoiceStatus::Void], true)
                        && bccomp($record?->balanceDue(), '0', 2) > 0),
            ])
            ->filters([
                Filter::make('patient')
                    ->label(__('Patient'))
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('search')
                            ->label(__('Search patient'))
                            ->placeholder(__('Name, MRN, phone or email...'))
                            ->live(onBlur: true),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['search'] ?? null)) {
                            return $query;
                        }
                        $search = $data['search'];
                        return $query->whereHas('patient', fn (Builder $q): Builder => $q
                            ->where('mrn', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                        );
                    }),
                Filter::make('created_at')
                    ->label(__('Created date'))
                    ->columns(2)
                    ->columnSpan(2)
                    ->schema([
                        DateTimePicker::make('created_from')
                            ->label(__('From'))
                            ->placeholder(__('From date'))
                            ->native(true),
                        DateTimePicker::make('created_until')
                            ->label(__('Until'))
                            ->placeholder(__('To date'))
                            ->native(true),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn (Builder $q, $date): Builder => $q->where('created_at', '>=', $date))
                            ->when($data['created_until'], fn (Builder $q, $date): Builder => $q->where('created_at', '<=', $date));
                    }),
                SelectFilter::make('payment_status')
                    ->label(__('Payment'))
                    ->options([
                        InvoiceStatus::Draft->value => InvoiceStatus::Draft->getLabel(),
                        InvoiceStatus::Issued->value => InvoiceStatus::Issued->getLabel(),
                        InvoiceStatus::PartiallyPaid->value => InvoiceStatus::PartiallyPaid->getLabel(),
                        InvoiceStatus::Paid->value => InvoiceStatus::Paid->getLabel(),
                        InvoiceStatus::Void->value => InvoiceStatus::Void->getLabel(),
                        'all' => __('All'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (($data['value'] ?? null) === 'unpaid') {
                            $query->whereRaw('CAST(total AS DECIMAL(14,2)) > CAST(amount_paid AS DECIMAL(14,2))');
                        }
                    }),
                SelectFilter::make('source')
                    ->label(__('Source'))
                    ->options([
                        'pharmacy' => __('Pharmacy'),
                        'encounter' => __('Encounter'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (($data['value'] ?? null) === 'pharmacy') {
                            $query->where('metadata->source', 'pharmacy_pos');
                        } elseif (($data['value'] ?? null) === 'encounter') {
                            $query->whereNotNull('encounter_id');
                        }
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->defaultSort('issued_at', 'asc')
            ->paginated([10, 25, 50,100])
            ->searchable();
    }



    protected function getHeaderActions(): array
    {
        return [
            RecordInvoicePaymentAction::make()
                ->visible(fn (): bool => $this->selectedInvoice !== null
                    && ! in_array($this->selectedInvoice['status'], [InvoiceStatus::Void->value], true)
                    && (float) $this->selectedInvoice['balance_due'] > 0),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Billing Desk');
    }
}
