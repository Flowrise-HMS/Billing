<?php

namespace Modules\Billing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Billing\Models\PaymentIntent;

class PaymentCheckoutSessionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public PaymentIntent $intent) {}
}
