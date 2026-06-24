<?php

namespace Modules\Billing\Gateways\Paystack;

use MusheAbdulHakim\Paystack\Paystack;

class PaystackClientFactory
{
    public function make(string $secretKey)
    {
        return Paystack::client($secretKey);
    }
}
