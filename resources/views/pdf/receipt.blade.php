<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Payment receipt') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .muted { color: #6b7280; }
        .section { margin-top: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 700; }
        .right { text-align: right; }
    </style>
</head>
<body>
    @include('core::print.partials.pdf-brand-header', [
        'branchId' => $payment->branch_id,
        'subtitle' => $payment->branch?->name,
    ])
    <h1>{{ __('Payment receipt') }}</h1>

    <div class="section">
        <strong>{{ __('Receipt ID') }}:</strong> {{ $payment->id }}<br>
        <strong>{{ __('Date') }}:</strong> {{ optional($payment->received_at)->format('Y-m-d H:i') }}<br>
        <strong>{{ __('Method') }}:</strong> {{ strtoupper((string) $payment->method?->value ?? (string) $payment->method) }}<br>
        <strong>{{ __('Gateway') }}:</strong> {{ strtoupper($payment->gateway) }}<br>
        <strong>{{ __('Amount') }}:</strong> {{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}
    </div>

    <div class="section">
        @include('core::print.partials.client-identity', ['client' => $client])
        <br>
        <strong>{{ __('Branch') }}:</strong> {{ $payment?->branch?->name ?? __('N/A') }}
    </div>

    <div class="section">
        <strong>{{ __('Applied invoices') }}</strong>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Invoice #') }}</th>
                    <th>{{ __('Line') }}</th>
                    <th class="right">{{ __('Allocated amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($allocations as $allocation)
                    <tr>
                        <td>{{ $allocation->invoiceLine?->invoice?->invoice_number ?? '-' }}</td>
                        <td>{{ $allocation->invoiceLine?->description ?? '-' }}</td>
                        <td class="right">{{ $payment->currency }} {{ number_format((float) $allocation->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">{{ __('No allocations recorded for this payment.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>

