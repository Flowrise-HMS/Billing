<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body>
<p>{{ __('Invoice :number has been issued. Amount due: :amount :currency', ['number' => $invoice->invoice_number, 'amount' => $balance, 'currency' => $invoice->currency]) }}</p>
@if($invoice->patient)
<p>{{ __('MRN') }}: {{ $invoice->patient->mrn }}</p>
@endif
<p>{{ config('app.name') }}</p>
</body>
</html>
