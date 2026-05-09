<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Invoice') }} {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .muted { color: #6b7280; }
        .section { margin-top: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 700; }
        .right { text-align: right; }
        .totals td { border: none; padding: 4px 0; }
        .totals .right { font-weight: 700; }
    </style>
</head>
<body>
    <h1>{{ __('Invoice') }}</h1>
    <div class="muted">{{ config('app.name') }}</div>
    @if($invoice->branch?->name)
        <div class="muted">{{ $invoice->branch->name }}</div>
    @endif

    <div class="section">
        <strong>{{ __('Invoice #') }}:</strong> {{ $invoice->invoice_number }}<br>
        <strong>{{ __('Status') }}:</strong> {{ $invoice->status?->getLabel() ?? $invoice->status }}<br>
        @if($invoice->issued_at)
            <strong>{{ __('Issued') }}:</strong> {{ $invoice->issued_at->format('Y-m-d H:i') }}<br>
        @endif
        @if($invoice->encounter_discharged_at)
            <strong>{{ __('Encounter discharged') }}:</strong> {{ $invoice->encounter_discharged_at->format('Y-m-d H:i') }}<br>
        @endif
        <strong>{{ __('Currency') }}:</strong> {{ $invoice->currency }}
    </div>

    <div class="section">
        <strong>{{ __('Patient') }}:</strong>
        {{ $invoice->patient?->full_name ?? __('N/A') }}
        @if ($invoice->patient?->mrn)
            ({{ __('MRN') }}: {{ $invoice->patient->mrn }})
        @endif
        <br>
        @if($invoice->encounter?->encounter_number)
            <strong>{{ __('Encounter') }}:</strong> {{ $invoice->encounter->encounter_number }}<br>
        @endif
    </div>

    <div class="section">
        <strong>{{ __('Line items') }}</strong>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Description') }}</th>
                    <th class="right">{{ __('Qty') }}</th>
                    <th class="right">{{ __('Unit') }}</th>
                    <th class="right">{{ __('Line total') }}</th>
                    <th>{{ __('Line status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->lines as $line)
                    <tr>
                        <td>{{ $line->description ?? $line->service?->name ?? '-' }}</td>
                        <td class="right">{{ $line->quantity }}</td>
                        <td class="right">{{ number_format((float) $line->unit_price, 2) }}</td>
                        <td class="right">{{ number_format((float) $line->line_total, 2) }}</td>
                        <td>{{ $line->line_status?->getLabel() ?? (string) $line->line_status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">{{ __('No lines.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <table class="totals" style="width: 50%; margin-left: auto;">
            <tr>
                <td>{{ __('Subtotal') }}</td>
                <td class="right">{{ number_format((float) $invoice->subtotal, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td>{{ __('Discount') }}</td>
                <td class="right">{{ number_format((float) $invoice->discount_total, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td>{{ __('Tax') }}</td>
                <td class="right">{{ number_format((float) $invoice->tax_total, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td>{{ __('Total') }}</td>
                <td class="right">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td>{{ __('Amount paid') }}</td>
                <td class="right">{{ number_format((float) $invoice->amount_paid, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td>{{ __('Balance due') }}</td>
                <td class="right">{{ number_format((float) $invoice->balanceDue(), 2) }} {{ $invoice->currency }}</td>
            </tr>
        </table>
    </div>

    <div class="section muted">
        {{ __('Generated on') }} {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
