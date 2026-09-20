<?php

namespace Modules\Billing\Support;

use Modules\Billing\Enums\PaymentMethod;

/**
 * Payment method choices for the manual collection forms, limited to the
 * methods enabled on the Billing settings page (gateway is never manual).
 */
class PaymentMethodOptions
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $enabled = app_settings()->billingPaymentMethods();

        return collect(PaymentMethod::cases())
            ->reject(fn (PaymentMethod $method): bool => $method === PaymentMethod::Gateway)
            ->filter(fn (PaymentMethod $method): bool => in_array($method->value, $enabled, true))
            ->mapWithKeys(fn (PaymentMethod $method): array => [$method->value => $method->getLabel()])
            ->all();
    }

    public static function default(): string
    {
        return app_settings()->defaultBillingPaymentMethod();
    }
}
