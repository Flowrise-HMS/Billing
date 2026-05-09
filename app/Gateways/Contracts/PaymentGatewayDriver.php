<?php

namespace Modules\Billing\Gateways\Contracts;

use Illuminate\Http\Request;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\PaymentIntent;

interface PaymentGatewayDriver
{
    public function key(): string;

    public function createCheckout(BranchPaymentGatewayConfig $config, PaymentIntent $intent): PaymentIntent;

    public function verifyWebhookSignature(Request $request, BranchPaymentGatewayConfig $config): bool;

    /**
     * @return array{reference: string, amount_minor?: int, currency?: string, success: bool}|null
     */
    public function parseWebhookPayload(Request $request): ?array;
}
