<?php

namespace Modules\Billing\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Billing\Enums\PaymentPlanStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentPlan;
use Modules\Billing\Models\PaymentPlanInstallment;
use Modules\Billing\Services\PaymentPlanService;
use Modules\Core\Http\Controllers\Api\ResourceApiController;
use Modules\Core\Http\Resources\FieldListTransformer;
use RuntimeException;

/**
 * @group Billing
 *
 * Instalment payment plans on issued invoices.
 */
class PaymentPlanController extends ResourceApiController
{
    protected string $branchScope = 'via:invoice';

    protected array $with = ['installments'];

    public function __construct(protected PaymentPlanService $plans) {}

    public static function model(): string
    {
        return PaymentPlan::class;
    }

    public function fields(): array
    {
        return ['id', 'invoice_id', 'total_amount', 'down_payment', 'installment_count', 'frequency_days', 'status', 'start_date', 'notes', 'created_at'];
    }

    public function filters(): array
    {
        return ['invoice_id' => 'invoice_id', 'status' => 'status'];
    }

    public function storeRules(): array
    {
        return [
            'invoice_id' => ['required', 'uuid'],
            'installment_count' => ['required', 'integer', 'min:2', 'max:60'],
            'frequency_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function createRecord(array $data): Model
    {
        $invoice = Invoice::query()->findOrFail($data['invoice_id']);

        try {
            return $this->plans->createPlan(
                $invoice,
                (int) $data['installment_count'],
                (int) ($data['frequency_days'] ?? 30),
                number_format((float) ($data['down_payment'] ?? 0), 2, '.', ''),
                $data['notes'] ?? null,
            );
        } catch (RuntimeException $e) {
            throw new \DomainException($e->getMessage(), 0, $e);
        }
    }

    protected function present(Model $record, Request $request): array
    {
        return [
            ...parent::present($record, $request),
            'installments' => $record->installments->map(fn (PaymentPlanInstallment $installment): array => FieldListTransformer::using($installment, [
                'id', 'installment_number', 'due_date', 'amount', 'paid_amount', 'status', 'paid_at',
            ])->toArray($request))->values()->all(),
        ];
    }

    /**
     * Cancel the plan.
     */
    public function cancel(string $id): JsonResponse
    {
        return $this->act($id, 'update', function (PaymentPlan $plan): PaymentPlan {
            $this->requireStatus($plan, [PaymentPlanStatus::Active], 'Only active payment plans can be cancelled.');
            $this->plans->cancelPlan($plan);

            return $plan->refresh();
        });
    }
}
