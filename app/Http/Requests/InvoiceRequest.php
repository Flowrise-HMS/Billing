<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $invoiceId = $this->route('invoice')?->id;

        return [
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'patient_id' => ['nullable', 'uuid', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'uuid', 'exists:encounters,id'],
            'appointment_id' => ['nullable', 'uuid', 'exists:appointments,id'],
            'invoice_number' => ['nullable', 'string', 'max:50', Rule::unique('invoices', 'invoice_number')->ignore($invoiceId)],
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'invoice_type' => ['nullable', Rule::enum(InvoiceType::class)],
            'currency' => ['nullable', 'string', 'max:10'],
            'issued_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:50'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'lines' => ['nullable', 'array'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:1000'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.total' => ['nullable', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'organization_id.required' => 'Organization is required.',
            'branch_id.required' => 'Branch is required.',
            'due_at.after_or_equal' => 'Due date cannot be before issue date.',
        ];
    }
}
