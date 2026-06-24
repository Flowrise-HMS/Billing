<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body>
<p>{{ __('Hello,') }}</p>
<p>{{ __('Your invoice :number has been updated.', ['number' => $invoice->invoice_number]) }}</p>
<p>{{ __('New line: :service — quantity :qty — line total :line_total :currency', [
    'service' => $serviceName,
    'qty' => $line->quantity,
    'line_total' => $line->line_total,
    'currency' => $invoice->currency,
]) }}</p>
<p>{{ __('Invoice total: :total :currency. Balance due: :balance :currency.', [
    'total' => $invoice->total,
    'balance' => $invoice->balanceDue(),
    'currency' => $invoice->currency,
]) }}</p>
<p><a href="{{ $pdfUrl }}">{{ __('View invoice PDF') }}</a></p>
@if($checkoutUrl ?? false)
<p><a href="{{ $checkoutUrl }}" style="display: inline-block; padding: 10px 20px; background-color: #10b981; color: #fff; text-decoration: none; border-radius: 6px;">{{ __('Pay now') }}</a></p>
@endif
<p>{{ config('app.name') }}</p>
</body>
</html>
