<?php

namespace Modules\Billing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Billing\Models\InvoiceLine;

class InvoiceLineRemoved
{
    use Dispatchable, SerializesModels;

    public function __construct(public InvoiceLine $line) {}
}
