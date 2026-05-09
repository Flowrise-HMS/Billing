<?php

namespace Modules\Billing\Gateways;

use InvalidArgumentException;
use Modules\Billing\Gateways\Contracts\PaymentGatewayDriver;
use Modules\Billing\Gateways\Drivers\FlutterwaveDriver;
use Modules\Billing\Gateways\Drivers\HubtelDriver;
use Modules\Billing\Gateways\Drivers\PaystackDriver;
use Modules\Billing\Gateways\Drivers\StripeDriver;

class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGatewayDriver>> */
    protected array $drivers = [
        'paystack' => PaystackDriver::class,
        'stripe' => StripeDriver::class,
        'flutterwave' => FlutterwaveDriver::class,
        'hubtel' => HubtelDriver::class,
    ];

    public function driver(string $key): PaymentGatewayDriver
    {
        $key = strtolower($key);
        if (! isset($this->drivers[$key])) {
            throw new InvalidArgumentException("Unknown payment gateway driver [{$key}].");
        }

        return app($this->drivers[$key]);
    }
}
