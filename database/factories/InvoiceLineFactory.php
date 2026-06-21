<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Core\Models\Service;

class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 10, 2000);
        $quantity = fake()->numberBetween(1, 10);
        $lineTotal = round($unitPrice * $quantity, 2);

        return [
            'invoice_id' => Invoice::factory(),
            'service_id' => Service::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'line_status' => InvoiceLineStatus::Unpaid,
            'patient_responsibility_amount' => $lineTotal,
        ];
    }
}
