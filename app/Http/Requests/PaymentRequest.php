<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\PaymentMethod;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'uuid', 'exists:patients,id'],
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'gateway' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'provider_transaction_id' => ['nullable', 'string', 'max:255'],
            'received_at' => ['nullable', 'date'],
            'recorded_by' => ['nullable', 'integer', 'exists:users,id'],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['uuid', 'exists:invoices,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Branch is required.',
            'method.required' => 'Payment method is required.',
            'amount.required' => 'Amount is required.',
            'amount.min' => 'Amount must be greater than zero.',
        ];
    }
}
