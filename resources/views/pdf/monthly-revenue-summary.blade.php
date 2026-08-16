<h1>Monthly Revenue Summary — {{ $month }}</h1>
<p>{{ $branch?->name }}</p>
<table>
    <thead>
        <tr><th>Metric</th><th>Amount</th></tr>
    </thead>
    <tbody>
        <tr><td>Revenue total</td><td>{{ number_format((float) ($summary['revenue_total'] ?? 0), 2) }}</td></tr>
        <tr><td>Refunds</td><td>{{ number_format((float) ($summary['refunds_total'] ?? 0), 2) }}</td></tr>
        <tr><td>Net revenue</td><td>{{ number_format((float) ($summary['net_revenue'] ?? 0), 2) }}</td></tr>
        @foreach (($summary['revenue_by_method'] ?? []) as $method => $amount)
            <tr><td>Revenue - {{ $method }}</td><td>{{ number_format((float) $amount, 2) }}</td></tr>
        @endforeach
    </tbody>
</table>
