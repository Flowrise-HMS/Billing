<?php

namespace Modules\Billing\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Events\UnpaidBillingNoticeRequired;
use Modules\Billing\Models\Invoice;
use Modules\Core\Support\AppSettings;

class FlagOverdueInvoices extends Command
{
    protected $signature = 'invoices:check-overdue
        {--dry-run : List matching invoices without sending notifications}';

    protected $description = 'Mark issued/partially-paid invoices past due_at as overdue and send reminders';

    public function handle(): int
    {
        $cooldownDays = 7;

        try {
            $cooldownDays = app(AppSettings::class)->billing()->overdue_reminder_cooldown_days;
        } catch (\Throwable) {
            // use default
        }

        $cooldownCutoff = now()->subDays($cooldownDays);

        $overdue = Invoice::query()
            ->withoutGlobalScopes()
            ->overdue()
            ->where(function ($q) use ($cooldownCutoff) {
                $q->whereNull('last_unpaid_reminder_at')
                    ->orWhere('last_unpaid_reminder_at', '<=', $cooldownCutoff);
            })
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
                $invoice->update(['last_unpaid_reminder_at' => now()]);
            }
        }

        if (! $this->option('dry-run')) {
            $this->info('Overdue reminders dispatched.');
        }

        return self::SUCCESS;
    }
}
