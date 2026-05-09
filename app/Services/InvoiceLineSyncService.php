<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Events\InvoiceLineAdded;
use Modules\Billing\Events\InvoiceLineUpdated;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Contracts\InsurancePricingResolver;

class InvoiceLineSyncService
{
    public function __construct(
        protected EncounterInvoiceService $encounterInvoiceService,
        protected InvoiceTotalsService $totalsService,
        protected InsurancePricingResolver $insurancePricing
    ) {}

    public function syncFromRequestItem(RequestItem $item): void
    {
        $request = $item->serviceRequest;
        if (! $request || ! $request->encounter_id) {
            return;
        }

        $encounter = $request->encounter;
        if (! $encounter) {
            return;
        }

        $invoice = $this->encounterInvoiceService->ensureDraftInvoiceForEncounter($encounter);

        DB::transaction(function () use ($item, $invoice) {
            $line = InvoiceLine::query()->where('invoice_id', $invoice->id)
                ->where('billable_type', $item->getMorphClass())
                ->where('billable_id', $item->id)
                ->first();

            $lineTotal = (string) $item->total_price;
            $status = $item->status === RequestItemStatus::CANCELLED
                ? InvoiceLineStatus::Void
                : InvoiceLineStatus::Unpaid;

            $payload = [
                'service_id' => $item->service_id,
                'description' => $item->service_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount,
                'tax_amount' => 0,
                'line_total' => $lineTotal,
                'line_status' => $status,
                'patient_responsibility_amount' => $status === InvoiceLineStatus::Void ? 0 : $lineTotal,
            ];

            if ($status !== InvoiceLineStatus::Void && $item->patient_id) {
                $pricing = $this->insurancePricing->resolveForItem(
                    patientId: (string) $item->patient_id,
                    itemType: 'service',
                    externalCode: (string) $item->service_id,
                    fallbackAmount: $lineTotal
                );
                $payload['insurance_expected_amount'] = $pricing['insurer_amount'];
                $payload['patient_responsibility_amount'] = $pricing['patient_amount'];
                $payload['metadata'] = [
                    'insurance_policy_id' => $pricing['policy_id'],
                    'insurance_payer_id' => $pricing['payer_id'],
                    'insurance_source_version' => $pricing['source_version'],
                ];
            }

            if ($line) {
                $line->update($payload);
                $line = $line->fresh();
                DB::afterCommit(fn () => Event::dispatch(new InvoiceLineUpdated($line)));
            } else {
                $line = $invoice->lines()->create(array_merge($payload, [
                    'billable_type' => $item->getMorphClass(),
                    'billable_id' => $item->id,
                    'amount_paid' => 0,
                ]));
                DB::afterCommit(fn () => Event::dispatch(new InvoiceLineAdded($line)));
            }

            $this->totalsService->recalculate($invoice->fresh(['lines']));
        });
    }
}
