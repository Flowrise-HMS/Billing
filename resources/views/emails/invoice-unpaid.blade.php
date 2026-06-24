<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body>
<p>{{ __('Hello,') }}</p>
<p>{{ __('This is a reminder that invoice :number has an outstanding balance.', ['number' => $invoice->invoice_number]) }}</p>
<p>{{ __('Amount due: :amount :currency', ['amount' => $balance, 'currency' => $invoice->currency]) }}</p>
@if($invoice->patient)
<p>{{ __('MRN') }}: {{ $invoice->patient->mrn }}</p>
@endif
<p><a href="{{ $pdfUrl }}">{{ __('View invoice PDF') }}</a></p>
@if($checkoutUrl ?? false)
<p><a href="{{ $checkoutUrl }}" style="display: inline-block; padding: 10px 20px; background-color: #10b981; color: #fff; text-decoration: none; border-radius: 6px;">{{ __('Pay now') }}</a></p>
@endif
<p>{{ config('app.name') }}</p>
</body>
</html>
