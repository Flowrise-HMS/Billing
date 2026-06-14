<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Models\Invoice;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Organization;
use Modules\Patient\Models\Patient;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $issuedAt = fake()->dateTimeBetween('-30 days', 'now');
        $dueAt = (clone $issuedAt)->modify('+30 days');

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => Branch::factory(),
            'patient_id' => Patient::factory(),
            'invoice_type' => InvoiceType::PRIMARY,
            'invoice_number' => 'INV-' . strtoupper(fake()->bothify('####??')),
            'status' => InvoiceStatus::DRAFT,
            'issued_at' => $issuedAt,
            'due_at' => $dueAt,
            'subtotal' => fake()->randomFloat(2, 50, 5000),
            'tax_total' => fake()->randomFloat(2, 0, 500),
            'discount_total' => fake()->randomFloat(2, 0, 200),
            'total' => fake()->randomFloat(2, 50, 5000),
            'amount_paid' => 0,
            'lock_version' => 1,
        ];
    }
}
