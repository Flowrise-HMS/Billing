<?php

namespace Modules\Billing\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Events\UnpaidBillingNoticeRequired;
use Modules\Billing\Models\Invoice;

class FlagOverdueInvoices extends Command
{
    protected $signature = 'invoices:check-overdue
        {--dry-run : List matching invoices without sending notifications}';

    protected $description = 'Mark issued/partially-paid invoices past due_at as overdue and send reminders';

    public function handle(): int
    {
        $overdue = Invoice::query()
            ->withoutGlobalScopes()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue invoices found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d overdue invoice(s).', $overdue->count()));

        foreach ($overdue as $invoice) {
            $this->line(sprintf(
                '  %s — %s (due: %s, balance: %s)',
                $invoice->invoice_number,
                $invoice->patient?->display_name ?? 'N/A',
                $invoice->due_at->toDateString(),
                $invoice->balanceDue(),
            ));

            if (! $this->option('dry-run')) {
                Event::dispatch(new UnpaidBillingNoticeRequired($invoice));
            }
        }

        if (! $this->option('dry-run')) {
            $this->info('Overdue reminders dispatched.');
        }

        return self::SUCCESS;
    }
}
