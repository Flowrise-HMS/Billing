<?php

namespace Modules\Billing\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\InvoiceTotalsService;
use Modules\Billing\Services\ManualInvoiceService;
use Modules\Core\Http\Controllers\Api\ResourceApiController;
use Modules\Core\Http\Resources\FieldListTransformer;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;

/**
 * @group Billing
 *
 * Invoices with their lines. Standalone invoices are created as drafts, lines are
 * added while in draft, then the invoice is issued. Issued invoices are voided,
 * never deleted; only drafts can be deleted.
 */
class InvoiceController extends ResourceApiController
{
    protected array $with = ['lines'];

    public function __construct(
        protected ManualInvoiceService $manual,
        protected InvoiceIssuanceService $issuance,
        protected InvoiceTotalsService $totals,
    ) {}

    public static function model(): string
    {
        return Invoice::class;
    }

    public function fields(): array
    {
        return ['id', 'invoice_number', 'patient_id', 'encounter_id', 'appointment_id', 'status', 'invoice_type', 'currency',
            'subtotal', 'discount_total', 'tax_total', 'total', 'amount_paid', 'issued_at', 'due_at', 'created_at', 'updated_at'];
    }

    public function filters(): array
    {
        return ['patient_id' => 'patient_id', 'encounter_id' => 'encounter_id', 'status' => 'status', 'invoice_type' => 'invoice_type'];
    }

    public function searchable(): array
    {
        return ['invoice_number'];
    }

    public function sortable(): array
    {
        return ['created_at', 'issued_at', 'due_at', 'total'];
    }

    public function storeRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'currency' => ['nullable', 'string', 'size:3'],
            'due_at' => ['nullable', 'date'],
        ];
    }

    public function updateRules(?Model $record = null): array
    {
        return ['due_at' => ['sometimes', 'nullable', 'date']];
    }

    protected function createRecord(array $data): Model
    {
        $patient = Patient::query()->findOrFail($data['patient_id']);
        $invoice = $this->manual->createStandaloneDraft((string) $this->currentBranchId(), $patient->id, $data['currency'] ?? 'GHS');

        if (isset($data['due_at'])) {
            $invoice->update(['due_at' => $data['due_at']]);
        }

        return $invoice;
    }

    protected function updateRecord(Model $record, array $data): Model
    {
        $this->manual->assertDraft($record);

        return parent::updateRecord($record, $data);
    }

    protected function deleteRecord(Model $record): void
    {
        $this->manual->assertDraft($record);
        $record->lines()->delete();
        $record->delete();
    }

    protected function present(Model $record, Request $request): array
    {
        return [
            ...parent::present($record, $request),
            'balance_due' => $record->balanceDue(),
            'lines' => $record->lines->map(fn (InvoiceLine $line): array => FieldListTransformer::using($line, [
                'id', 'service_id', 'description', 'quantity', 'unit_price', 'discount_amount', 'tax_amount', 'line_total',
                'amount_paid', 'line_status', 'insurance_expected_amount', 'patient_responsibility_amount',
            ])->toArray($request))->values()->all(),
        ];
    }

    /**
     * Add a line to a draft invoice.
     *
     * @bodyParam service_id string Billable service; its price is used unless unit_price is given. Example: 9d0c6b0e-...
     * @bodyParam description string required when no service_id. Example: Dressing materials
     * @bodyParam quantity integer Example: 2
     * @bodyParam unit_price number Example: 15.50
     * @bodyParam discount_amount number Example: 0
     */
    public function addLine(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['nullable', 'uuid', 'exists:services,id'],
            'description' => ['required_without:service_id', 'nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $this->act($id, 'update', function (Invoice $invoice) use ($data): Invoice {
            $this->manual->assertDraft($invoice);
            $service = isset($data['service_id']) ? Service::query()->find($data['service_id']) : null;
            $quantity = (int) ($data['quantity'] ?? 1);
            $unitPrice = (string) ($data['unit_price'] ?? $service?->getDefaultPrice() ?? '0');
            $discount = (string) ($data['discount_amount'] ?? '0');

            if (bccomp($discount, bcmul($unitPrice, (string) $quantity, 2), 2) > 0) {
                throw new \DomainException('The discount exceeds the line amount.');
            }

            $invoice->lines()->create([
                'service_id' => $service?->id,
                'description' => $data['description'] ?? $service?->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'original_unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_amount' => '0',
                'line_total' => bcsub(bcmul($unitPrice, (string) $quantity, 2), $discount, 2),
                'amount_paid' => '0',
                'line_status' => InvoiceLineStatus::Unpaid,
            ]);

            return $this->totals->recalculate($invoice->fresh(['lines']));
        });
    }

    /**
     * Remove a line from a draft invoice.
     *
     * @bodyParam line_id string required Example: 9d0c6b0e-...
     */
    public function removeLine(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['line_id' => ['required', 'uuid']]);

        return $this->act($id, 'update', function (Invoice $invoice) use ($data): Invoice {
            $this->manual->assertDraft($invoice);
            $invoice->lines()->whereKey($data['line_id'])->firstOrFail()->delete();

            return $this->totals->recalculate($invoice->fresh(['lines']));
        });
    }

    /**
     * Issue a draft invoice.
     */
    public function issue(string $id): JsonResponse
    {
        return $this->act($id, 'update', fn (Invoice $invoice) => $this->issuance->issue($invoice));
    }

    /**
     * Void an issued, unpaid invoice (cancels its payment plan).
     */
    public function void(string $id): JsonResponse
    {
        return $this->act($id, 'void', fn (Invoice $invoice) => $this->issuance->void($invoice));
    }
}
