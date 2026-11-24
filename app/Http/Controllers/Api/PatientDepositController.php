<?php

namespace Modules\Billing\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PatientDeposit;
use Modules\Billing\Services\DepositApplicationService;
use Modules\Billing\Services\DepositRecordingService;
use Modules\Core\Http\Controllers\Api\ResourceApiController;
use Modules\Patient\Models\Patient;

/**
 * @group Billing
 *
 * Patient deposits (prepayments) and applying them to invoices.
 */
class PatientDepositController extends ResourceApiController
{
    public function __construct(
        protected DepositRecordingService $deposits,
        protected DepositApplicationService $applications,
    ) {}

    public static function model(): string
    {
        return PatientDeposit::class;
    }

    public static function label(): string
    {
        return 'deposit';
    }

    public function fields(): array
    {
        return ['id', 'patient_id', 'payment_id', 'amount', 'unallocated_balance', 'currency', 'status', 'recorded_by', 'created_at'];
    }

    public function filters(): array
    {
        return ['patient_id' => 'patient_id', 'status' => 'status'];
    }

    public function storeRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function createRecord(array $data): Model
    {
        $patient = Patient::query()->findOrFail($data['patient_id']);

        return $this->deposits->record(
            $patient->id,
            (string) $this->currentBranchId(),
            number_format((float) $data['amount'], 2, '.', ''),
            $data['method'],
            $data['reference'] ?? null,
            auth()->id(),
        );
    }

    /**
     * Apply part of the deposit to an invoice.
     *
     * @bodyParam invoice_id string required Example: 9d0c6b0e-...
     * @bodyParam amount number required Example: 50.00
     */
    public function apply(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        return $this->act($id, 'update', function (PatientDeposit $deposit) use ($data): PatientDeposit {
            $invoice = Invoice::query()->findOrFail($data['invoice_id']);
            $this->applications->apply($deposit, $invoice, number_format((float) $data['amount'], 2, '.', ''), null, auth()->id());

            return $deposit->refresh();
        });
    }
}
