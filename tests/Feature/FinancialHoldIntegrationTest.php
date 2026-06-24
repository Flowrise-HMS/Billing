<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Database\Factories\RequestItemFactory;
use Modules\Clinical\Database\Factories\ServiceRequestFactory;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Database\Factories\ServiceFactory;
use Modules\Core\Settings\BillingSettings;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class FinancialHoldIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Billing']);
        $this->enableFinancialHold();
    }

    public function test_unpaid_prepay_request_item_blocks_fulfillment(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $user = User::factory()->create(['branch_id' => $branch->id]);

        $encounter = EncounterFactory::new()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
        ]);

        $service = ServiceFactory::new()->create([
            'requires_payment_before' => true,
        ]);

        $request = ServiceRequestFactory::new()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'encounter_id' => $encounter->id,
        ]);

        $item = RequestItemFactory::new()->create([
            'service_request_id' => $request->id,
            'service_id' => $service->id,
        ]);

        $invoice = Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
            'status' => InvoiceStatus::Draft,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'total' => 50,
            'amount_paid' => 0,
        ]);

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'billable_type' => (new RequestItem)->getMorphClass(),
            'billable_id' => $item->id,
            'service_id' => $service->id,
            'description' => 'Prepay service',
            'quantity' => 1,
            'unit_price' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 50,
            'amount_paid' => 0,
            'line_status' => InvoiceLineStatus::Unpaid,
            'patient_responsibility_amount' => 50,
        ]);

        $this->assertTrue($item->fresh()->hasActiveFinancialHold());

        $this->expectException(\RuntimeException::class);
        $item->markAsFulfilled($user->id);
    }

    protected function enableFinancialHold(): void
    {
        $settings = app(BillingSettings::class);
        $settings->financial_hold_enabled = true;
        $settings->save();
    }
}
