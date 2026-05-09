<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body>
<p>{{ __('Hello,') }}</p>
<p>{{ __('Invoice :number has been issued.', ['number' => $invoice->invoice_number]) }}</p>
<p>{{ __('Amount due: :amount :currency', ['amount' => $balance, 'currency' => $invoice->currency]) }}</p>
@if($invoice->patient)
<p>{{ __('MRN') }}: {{ $invoice->patient->mrn }}</p>
@endif
<p><a href="{{ $pdfUrl }}">{{ __('View invoice PDF') }}</a></p>
<p>{{ config('app.name') }}</p>
</body>
</html>
