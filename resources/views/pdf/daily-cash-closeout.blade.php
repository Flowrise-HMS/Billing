<h1>Daily Cash Closeout — {{ $summaryDate }}</h1>
<p>{{ $branch?->name }}</p>
<table>
    <thead>
        <tr><th>Cashier</th><th>Opening</th><th>Cash-in</th><th>Cash refunds</th><th>Change</th><th>Expected closing</th></tr>
    </thead>
    <tbody>
        @foreach ($cashiers as $figures)
            <tr>
                <td>{{ $figures['cashier_name'] }}</td>
                <td>{{ number_format((float) $figures['opening_cash'], 2) }}</td>
                <td>{{ number_format((float) $figures['cash_in'], 2) }}</td>
                <td>{{ number_format((float) $figures['cash_refunds'], 2) }}</td>
                <td>{{ number_format((float) $figures['change_given'], 2) }}</td>
                <td>{{ number_format((float) $figures['expected_closing'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
