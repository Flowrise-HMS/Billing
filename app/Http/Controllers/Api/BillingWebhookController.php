<?php

namespace Modules\Billing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Billing\Services\WebhookPaymentService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class BillingWebhookController extends Controller
{
    public function __construct(
        protected WebhookPaymentService $webhookPaymentService
    ) {}

    public function handle(Request $request, string $driver, string $branch): Response
    {
        try {
            $this->webhookPaymentService->process($request, $driver, $branch);
        } catch (Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                return response('', $e->getStatusCode());
            }
            report($e);

            return response('error', 500);
        }

        return response('ok', 200);
    }
}
