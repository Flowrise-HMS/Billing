<?php

namespace Modules\Billing\Filament\Clusters\Billing\Pages;

use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\DailyCashSummaryStatus;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Models\DailyCashSummary;
use Modules\Billing\Services\DailyCashCloseoutService;
use Modules\Core\Models\Branch;

class DailyCashCloseout extends Page
{
    use AuthorizesRequests;
    use HasPageShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected string $view = 'billing::filament.clusters.billing.pages.daily-cash-closeout';

    public ?string $summaryDate = null;

    public ?string $branchId = null;

    public ?string $openingCash = '0.00';

    public ?string $countedClosing = null;

    /**
     * @var array<string, mixed>
     */
    public array $closeout = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $cashiers = [];

    public int $staleCount = 0;

    public function mount(): void
    {
        $this->summaryDate = request()->query('summary_date', now()->toDateString());
        $this->branchId = request()->query('branch_id', Auth::user()?->branch_id);
    }

    public function loadCloseout(): void
    {
        if ($this->branchId === null) {
            $this->closeout = [];
            $this->cashiers = [];
            $this->staleCount = 0;

            return;
        }

        $service = app(DailyCashCloseoutService::class);
        $branch = Branch::query()->findOrFail($this->branchId);
        $cashier = Auth::user();

        $figures = $service->compute($branch, $cashier, $this->date());

        $summary = DailyCashSummary::query()
            ->where('branch_id', $branch->id)
            ->where('cashier_id', $cashier->id)
            ->where('summary_date', $this->summaryDate)
            ->latest('finalized_at')
            ->first();

        $this->staleCount = $summary?->finalized_at
            ? $service->postFinalizeTransactions($branch, $cashier, $this->date(), $summary->finalized_at)->count()
            : 0;

        $expected = bcadd($this->openingCash ?? '0', $figures['expected_closing'] ?? '0', 2);

        $this->closeout = $figures;
        $this->cashiers = [
            (string) $cashier->id => [
                'cashier_name' => $cashier->name,
                'opening_cash' => $this->openingCash,
                'cash_in' => $figures['cash_in'],
                'cash_refunds' => $figures['cash_refunds'],
                'change_given' => $figures['change_given'],
                'expected_closing' => $expected,
                'variance' => $this->countedClosing !== null
                    ? bcsub($this->countedClosing, $expected, 2)
                    : '0',
                'status' => $summary?->status->value ?? DailyCashSummaryStatus::Open->value,
            ],
        ];
    }

    public function finalizeCashier(): void
    {
        $cashier = Auth::user();
        $summary = DailyCashSummary::firstOrNew([
            'branch_id' => $this->branchId,
            'cashier_id' => $cashier->id,
            'summary_date' => $this->summaryDate,
        ]);

        $this->authorize('update', $summary);

        $expected = bcadd($this->openingCash ?? '0', $this->closeout['expected_closing'] ?? '0', 2);
        $variance = $this->countedClosing !== null
            ? bcsub($this->countedClosing, $expected, 2)
            : '0';

        $summary->fill([
            'opening_cash' => $this->openingCash ?? '0',
            'change_given' => $this->closeout['change_given'] ?? '0',
            'counted_closing' => $this->countedClosing,
            'expected_closing' => $expected,
            'variance' => $variance,
            'status' => DailyCashSummaryStatus::Finalized,
            'finalized_at' => now(),
            'finalized_by' => Auth::id(),
        ]);
        $summary->save();

        $this->loadCloseout();
    }

    public function reopenCashier(): void
    {
        $cashier = Auth::user();
        $summary = DailyCashSummary::query()
            ->where('branch_id', $this->branchId)
            ->where('cashier_id', $cashier->id)
            ->where('summary_date', $this->summaryDate)
            ->firstOrFail();

        $this->authorize('update', $summary);

        $summary->update([
            'status' => DailyCashSummaryStatus::Open,
            'finalized_at' => null,
            'finalized_by' => null,
            'reopened_at' => now(),
            'reopened_by' => Auth::id(),
        ]);

        $this->loadCloseout();
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $rows = $this->cashiers;

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Cashier', 'Opening', 'Cash-in', 'Cash refunds', 'Change given', 'Expected closing']);
            foreach ($rows as $figures) {
                fputcsv($out, [
                    $figures['cashier_name'],
                    $figures['opening_cash'],
                    $figures['cash_in'],
                    $figures['cash_refunds'],
                    $figures['change_given'],
                    $figures['expected_closing'],
                ]);
            }
            fclose($out);
        }, 'daily-cash-closeout-'.$this->summaryDate.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(): \Symfony\Component\HttpFoundation\Response
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('billing::pdf.daily-cash-closeout', [
            'summaryDate' => $this->summaryDate,
            'branch' => Branch::query()->find($this->branchId),
            'cashiers' => $this->cashiers,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('daily-cash-closeout-'.$this->summaryDate.'.pdf');
    }

    /**
     * @return array<string, string>
     */
    public function getBranchesProperty(): array
    {
        return Branch::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    private function date(): \Carbon\CarbonInterface
    {
        return \Illuminate\Support\Carbon::parse($this->summaryDate);
    }
}
