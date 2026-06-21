<x-filament-panels::page>
    <div class="flex flex-wrap gap-2 mb-4">
        <x-filament::button
            tag="a"
            :href="$this->presetUrl('today')"
            :color="$this->preset === 'today' ? 'primary' : 'gray'"
            size="sm"
        >
            {{ __('Today') }}
        </x-filament::button>
        <x-filament::button
            tag="a"
            :href="$this->presetUrl('week')"
            :color="$this->preset === 'week' ? 'primary' : 'gray'"
            size="sm"
        >
            {{ __('This week') }}
        </x-filament::button>
        <x-filament::button
            tag="a"
            :href="$this->presetUrl('month')"
            :color="$this->preset === 'month' ? 'primary' : 'gray'"
            size="sm"
        >
            {{ __('This month') }}
        </x-filament::button>
    </div>

    <form method="GET" class="grid gap-4 md:grid-cols-5">
        <input type="hidden" name="preset" value="custom">
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Start date') }}</label>
            <input
                type="date"
                name="start_date"
                value="{{ $this->startDate }}"
                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
            >
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('End date') }}</label>
            <input
                type="date"
                name="end_date"
                value="{{ $this->endDate }}"
                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
            >
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Branch') }}</label>
            <select
                name="branch_id"
                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
            >
                <option value="">{{ __('All branches') }}</option>
                @foreach ($this->branchOptions as $id => $name)
                    <option value="{{ $id }}" @selected($this->branchId === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Payment method') }}</label>
            <select
                name="payment_method"
                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
            >
                @foreach ($this->paymentMethodOptions as $value => $label)
                    <option value="{{ $value }}" @selected($this->paymentMethod === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <x-filament::button type="submit">{{ __('Apply') }}</x-filament::button>
            <x-filament::button
                tag="a"
                color="gray"
                href="{{ route('billing.reports.revenue.csv', [
                    'preset' => $this->preset,
                    'start_date' => $this->startDate,
                    'end_date' => $this->endDate,
                    'branch_id' => $this->branchId,
                    'payment_method' => $this->paymentMethod,
                ]) }}"
            >
                {{ __('Export CSV') }}
            </x-filament::button>
        </div>
    </form>

    <div class="grid gap-4 md:grid-cols-4 mt-6">
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Billed') }}</div>
            <div class="text-2xl font-semibold">{{ number_format((float) data_get($this->report, 'summary.billed', 0), 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Collected') }}</div>
            <div class="text-2xl font-semibold text-success-600">{{ number_format((float) data_get($this->report, 'summary.collected', 0), 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Outstanding') }}</div>
            <div class="text-2xl font-semibold text-danger-600">{{ number_format((float) data_get($this->report, 'summary.outstanding', 0), 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Collection rate') }}</div>
            <div class="text-2xl font-semibold">
                @if (data_get($this->report, 'summary.collection_rate') !== null)
                    {{ data_get($this->report, 'summary.collection_rate') }}%
                @else
                    —
                @endif
            </div>
        </x-filament::section>
    </div>

    @if ($this->getFooterWidgets())
        <div class="grid gap-4 md:grid-cols-2 mt-6">
            <x-filament-widgets::widgets
                :widgets="$this->getFooterWidgets()"
                :columns="['md' => 2, 'xl' => 2]"
            />
        </div>
    @endif

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('Branch summary') }}</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-2">{{ __('Branch') }}</th>
                        <th class="py-2">{{ __('Billed') }}</th>
                        <th class="py-2">{{ __('Collected') }}</th>
                        <th class="py-2">{{ __('Outstanding') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (data_get($this->report, 'branch_breakdown', []) as $row)
                        <tr class="border-b">
                            <td class="py-2">{{ $row['branch_name'] }}</td>
                            <td class="py-2">{{ number_format((float) ($row['billed'] ?? 0), 2) }}</td>
                            <td class="py-2">{{ number_format((float) ($row['total_collected'] ?? 0), 2) }}</td>
                            <td class="py-2">{{ number_format((float) ($row['outstanding'] ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-2 text-gray-500">{{ __('No data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('Collections by payment method') }}</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-2">{{ __('Method') }}</th>
                        <th class="py-2">{{ __('Collected') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (data_get($this->report, 'method_breakdown', []) as $row)
                        <tr class="border-b">
                            <td class="py-2">{{ $row['method'] }}</td>
                            <td class="py-2">{{ number_format((float) $row['total_collected'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="py-2 text-gray-500">{{ __('No data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('A/R aging buckets') }}</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-2">{{ __('Bucket') }}</th>
                        <th class="py-2">{{ __('Amount') }}</th>
                        <th class="py-2">{{ __('Invoices') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (data_get($this->report, 'aging', []) as $row)
                        <tr class="border-b">
                            <td class="py-2">{{ $row['bucket'] }}</td>
                            <td class="py-2">{{ number_format((float) $row['amount'], 2) }}</td>
                            <td class="py-2">{{ $row['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('Recent payments') }}</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-2">{{ __('Date') }}</th>
                        <th class="py-2">{{ __('Patient') }}</th>
                        <th class="py-2">{{ __('Branch') }}</th>
                        <th class="py-2">{{ __('Method') }}</th>
                        <th class="py-2">{{ __('Amount') }}</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (data_get($this->report, 'recent_payments', []) as $payment)
                        <tr class="border-b">
                            <td class="py-2">{{ $payment['received_at'] }}</td>
                            <td class="py-2">{{ $payment['patient_name'] }}</td>
                            <td class="py-2">{{ $payment['branch_name'] }}</td>
                            <td class="py-2">{{ $payment['method'] }}</td>
                            <td class="py-2">{{ number_format((float) $payment['amount'], 2) }} {{ $payment['currency'] }}</td>
                            <td class="py-2">
                                <a
                                    href="{{ route('billing.payments.receipt', $payment['id']) }}"
                                    class="text-primary-600 hover:underline text-xs"
                                    target="_blank"
                                >
                                    {{ __('Receipt') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-2 text-gray-500">{{ __('No payments in this period') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('Top outstanding invoices') }}</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-2">{{ __('Invoice #') }}</th>
                        <th class="py-2">{{ __('Patient') }}</th>
                        <th class="py-2">{{ __('Branch') }}</th>
                        <th class="py-2">{{ __('Issued') }}</th>
                        <th class="py-2">{{ __('Balance') }}</th>
                        <th class="py-2">{{ __('Days overdue') }}</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (data_get($this->report, 'top_outstanding', []) as $invoice)
                        <tr class="border-b">
                            <td class="py-2">{{ $invoice['invoice_number'] }}</td>
                            <td class="py-2">{{ $invoice['patient_name'] }}</td>
                            <td class="py-2">{{ $invoice['branch_name'] }}</td>
                            <td class="py-2">{{ $invoice['issued_at'] }}</td>
                            <td class="py-2">{{ number_format((float) $invoice['balance'], 2) }} {{ $invoice['currency'] }}</td>
                            <td class="py-2">{{ $invoice['days_overdue'] }}</td>
                            <td class="py-2">
                                <a
                                    href="{{ \Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource::getUrl('view', ['record' => $invoice['id']]) }}"
                                    class="text-primary-600 hover:underline text-xs"
                                >
                                    {{ __('View') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-2 text-gray-500">{{ __('No outstanding invoices') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
