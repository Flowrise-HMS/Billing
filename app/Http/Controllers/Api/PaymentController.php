<?php

namespace Modules\Billing\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\InvoiceAllocationBuilder;
use Modules\Billing\Services\PaymentRecordingService;
use Modules\Core\Http\Controllers\Api\ResourceApiController;

/**
 * @group Billing
 *
 * Payments. A payment is recorded against an invoice and allocated across its
 * unpaid lines; payments are never edited or deleted — use a refund.
 */
class PaymentController extends ResourceApiController
{
    protected string $defaultSort = '-received_at';

    public function __construct(
        protected PaymentRecordingService $payments,
        protected InvoiceAllocationBuilder $allocator,
    ) {}

    public static function model(): string
    {
        return Payment::class;
    }

    public function fields(): array
    {
        return ['id', 'patient_id', 'method', 'gateway', 'type', 'amount', 'currency', 'provider_transaction_id', 'received_at', 'recorded_by', 'created_at'];
    }

    public function filters(): array
    {
        return ['patient_id' => 'patient_id', 'method' => 'method', 'type' => 'type'];
    }

    public function sortable(): array
    {
        return ['received_at', 'created_at', 'amount'];
    }

    public function storeRules(): array
    {
        return [
            'invoice_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $method) => $method->value, array_filter(PaymentMethod::cases(), fn ($m) => $m !== PaymentMethod::Gateway)))],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function createRecord(array $data): Model
    {
        $invoice = Invoice::query()->findOrFail($data['invoice_id']);
        $amount = number_format((float) $data['amount'], 2, '.', '');

        if (bccomp($amount, $invoice->balanceDue(), 2) > 0) {
            throw new \InvalidArgumentException('The amount exceeds the invoice balance.');
        }

        $allocations = $this->allocator->allocateAmountAcrossUnpaidLines($invoice, $amount);

        return $this->payments->record(
            allocations: $allocations,
            method: PaymentMethod::from($data['method']),
            gateway: 'manual',
            currency: $invoice->currency ?? 'GHS',
            patientId: $invoice->patient_id,
            branchId: (string) $invoice->branch_id,
            recordedBy: auth()->id(),
            providerTransactionId: $data['reference'] ?? null,
            metadata: ['source' => 'api'],
        );
    }

    /**
     * Refund (part of) a payment.
     *
     * @bodyParam amount number required Example: 20.00
     * @bodyParam reason string Example: Service not rendered.
     */
    public function refund(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->act($id, 'create', fn (Payment $payment) => $this->payments->refund(
            $payment,
            number_format((float) $data['amount'], 2, '.', ''),
            $data['reason'] ?? null,
            auth()->id(),
            'api',
        ));
    }
}
