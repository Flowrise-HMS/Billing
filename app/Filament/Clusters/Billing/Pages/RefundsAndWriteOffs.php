<?php

namespace Modules\Billing\Filament\Clusters\Billing\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Models\Payment;
use Modules\Core\Models\Branch;

class RefundsAndWriteOffs extends Page
{
    use HasPageShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMinus;

    protected string $view = 'billing::filament.clusters.billing.pages.refunds-and-write-offs';

    public ?string $branchId = null;

    public function mount(): void
    {
        $this->branchId = request()->query('branch_id', Auth::user()?->branch_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRefunds(): array
    {
        return $this->rows(PaymentType::Refund);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWriteOffs(): array
    {
        return $this->rows(PaymentType::WriteOff);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(PaymentType $type): array
    {
        return Payment::query()
            ->where('type', $type)
            ->when($this->branchId !== null, fn ($query) => $query->where('branch_id', $this->branchId))
            ->with([
                'patient' => fn ($query) => $query->withoutGlobalScopes(),
                'recorder',
            ])
            ->orderByDesc('received_at')
            ->get()
            ->map(function (Payment $payment): array {
                return [
                    'received_at' => $payment->received_at?->format('Y-m-d H:i') ?? '',
                    'patient_name' => $payment->patient?->full_name ?? __('N/A'),
                    'mrn' => $payment->patient?->mrn ?? '',
                    'amount' => abs((float) $payment->amount),
                    'reason' => $payment->metadata['reason'] ?? null,
                    'method' => (string) $payment->method?->value,
                    'gateway' => $payment->gateway,
                    'recorded_by' => $payment->recorder?->name ?? __('N/A'),
                ];
            })
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
