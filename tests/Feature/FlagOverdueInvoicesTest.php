<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Events\UnpaidBillingNoticeRequired;
use Modules\Billing\Models\Invoice;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Settings\BillingSettings;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class FlagOverdueInvoicesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_command_dispatches_event_and_sets_reminder_timestamp(): void
    {
        Event::fake([UnpaidBillingNoticeRequired::class]);

        $invoice = $this->createOverdueInvoice();

        $this->artisan('invoices:check-overdue')
            ->assertSuccessful();

        Event::assertDispatched(UnpaidBillingNoticeRequired::class, fn ($e) => $e->invoice->id === $invoice->id);

        $invoice->refresh();
        $this->assertNotNull($invoice->last_unpaid_reminder_at);
    }

    public function test_cooldown_skips_recently_reminded_invoices(): void
    {
        Event::fake([UnpaidBillingNoticeRequired::class]);

        $invoice = $this->createOverdueInvoice([
            'last_unpaid_reminder_at' => now()->subDay(),
        ]);

        $this->setCooldownDays(7);

        $originalReminderAt = $invoice->last_unpaid_reminder_at;

        $this->artisan('invoices:check-overdue')
            ->assertSuccessful();

        Event::assertNotDispatched(UnpaidBillingNoticeRequired::class);
        $this->assertEquals($originalReminderAt?->toDateTimeString(), $invoice->fresh()->last_unpaid_reminder_at?->toDateTimeString());
    }

    public function test_dry_run_lists_without_writes(): void
    {
        Event::fake([UnpaidBillingNoticeRequired::class]);

        $invoice = $this->createOverdueInvoice();

        $this->artisan('invoices:check-overdue', ['--dry-run' => true])
            ->assertSuccessful();

        Event::assertNotDispatched(UnpaidBillingNoticeRequired::class);
        $this->assertNull($invoice->fresh()->last_unpaid_reminder_at);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOverdueInvoice(array $overrides = []): Invoice
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        return Invoice::withoutEvents(function () use ($branch, $patient, $overrides) {
            return Invoice::query()->withoutGlobalScopes()->create(array_merge([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
                'status' => InvoiceStatus::Issued,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
                'due_at' => now()->subDays(3),
                'total' => 100,
                'amount_paid' => 0,
            ], $overrides));
        });
    }

    private function setCooldownDays(int $days): void
    {
        $settings = app(BillingSettings::class);
        $settings->overdue_reminder_cooldown_days = $days;
        $settings->save();
    }
}
