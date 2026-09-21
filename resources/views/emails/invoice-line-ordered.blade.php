<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body>
<p>{{ __('Hello,') }}</p>
<p>{{ __(':kind ordered: :service — :currency :amount.', [
    'kind' => $orderKind,
    'service' => $serviceName,
    'currency' => $invoice->currency,
    'amount' => $amountDue,
]) }}</p>
@if($checkoutUrl ?? false)
<p>{{ __('You can pay for this online now, no need to visit the billing desk.') }}</p>
<p><a href="{{ $checkoutUrl }}" style="display: inline-block; padding: 10px 20px; background-color: #10b981; color: #fff; text-decoration: none; border-radius: 6px;">{{ __('Pay now') }}</a></p>
@endif
<p>{{ config('app.name') }}</p>
</body>
</html>
