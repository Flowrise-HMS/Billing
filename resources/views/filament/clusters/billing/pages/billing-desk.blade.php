<x-filament-panels::page>
    <div class="grid gap-6 md:grid-cols-5">
        {{-- Left panel: Table-based invoice queue --}}
        <div class="md:col-span-2">
            {{ $this->table }}
        </div>

        {{-- Right panel: Selected invoice details --}}
        <div class="md:col-span-3 space-y-4">
            @if ($selectedInvoice)
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <span>{{ $selectedInvoice['invoice_number'] }}</span>
                            <div class="flex items-center gap-2">
                                @if ($selectedInvoice['is_overdue'])
                                    <x-filament::badge color="danger">{{ __('Overdue') }}</x-filament::badge>
                                @endif
                                <x-filament::badge>{{ $selectedInvoice['status_label'] }}</x-filament::badge>
                            </div>
                        </div>
                    </x-slot>

                    <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                        <div>
                            <span class="text-gray-500">{{ __('Patient') }}:</span>
                            <span class="font-medium">{{ $selectedInvoice['patient_name'] }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ __('Issued') }}:</span>
                            <span class="font-medium">{{ $selectedInvoice['issued_at'] }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ __('Total') }}:</span>
                            <span class="font-medium">{{ $selectedInvoice['currency'] }} {{ number_format((float) $selectedInvoice['total'], 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ __('Paid') }}:</span>
                            <span class="font-medium">{{ $selectedInvoice['currency'] }} {{ number_format((float) $selectedInvoice['amount_paid'], 2) }}</span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-gray-500">{{ __('Balance due') }}:</span>
                            <span class="font-semibold text-danger-600 text-lg">
                                {{ $selectedInvoice['currency'] }} {{ number_format((float) $selectedInvoice['balance_due'], 2) }}
                            </span>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">{{ __('Line items') }}</x-slot>
                    {{ $this->getLineItemsTable() }}

                    @if ($selectedInvoice['payment_plan'])
                        <x-filament::section class="mt-4">
                            <x-slot name="heading">
                                <div class="flex items-center justify-between">
                                    <span>{{ __('Payment plan') }}</span>
                                    <x-filament::badge color="info">{{ __(':count installments', ['count' => $selectedInvoice['payment_plan']['installment_count']]) }}</x-filament::badge>
                                </div>
                            </x-slot>
                            <div class="overflow-x-auto">
                                <table class="fi-ta-table w-full">
                                    <thead>
                                        <tr class="fi-ta-table-header-row">
                                            <th class="fi-ta-table-header-cell px-3 py-3.5 text-sm font-semibold text-left">{{ '#' }}</th>
                                            <th class="fi-ta-table-header-cell px-3 py-3.5 text-sm font-semibold text-left">{{ __('Due date') }}</th>
                                            <th class="fi-ta-table-header-cell px-3 py-3.5 text-sm font-semibold text-right">{{ __('Amount') }}</th>
                                            <th class="fi-ta-table-header-cell px-3 py-3.5 text-sm font-semibold text-right">{{ __('Paid') }}</th>
                                            <th class="fi-ta-table-header-cell px-3 py-3.5 text-sm font-semibold text-center">{{ __('Status') }}</th>
                                            <th class="fi-ta-table-header-cell px-3 py-3.5 text-sm font-semibold text-right">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($selectedInvoice['payment_plan']['installments'] as $inst)
                                            <tr class="fi-ta-table-row border-b">
                                                <td class="fi-ta-table-cell px-3 py-2">{{ $inst['number'] }}</td>
                                                <td class="fi-ta-table-cell px-3 py-2">{{ $inst['due_date'] }}</td>
                                                <td class="fi-ta-table-cell px-3 py-2 text-right">{{ number_format((float) $inst['amount'], 2) }}</td>
                                                <td class="fi-ta-table-cell px-3 py-2 text-right">{{ number_format((float) $inst['paid_amount'], 2) }}</td>
                                                <td class="fi-ta-table-cell px-3 py-2 text-center">
                                                    <x-filament::badge color="{{ $inst['status_color'] }}">{{ $inst['status'] }}</x-filament::badge>
                                                </td>
                                                <td class="fi-ta-table-cell px-3 py-2 text-right">
                                                    @if (! $inst['is_paid'])
                                                        <x-filament::button
                                                            wire:click="mountAction('collectInstallment', { installment_id: '{{ $inst['id'] }}' })"
                                                            color="success"
                                                            size="xs"
                                                            icon="heroicon-m-currency-dollar"
                                                        >
                                                            {{ __('Collect') }}
                                                        </x-filament::button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-filament::section>
                    @endif

                    <div class="mt-4 flex justify-end gap-3">
                        <x-filament::button
                            tag="a"
                            href="{{ route('billing.invoices.pdf', $selectedInvoice['id']) }}"
                            target="_blank"
                            color="gray"
                            icon="heroicon-m-printer"
                        >
                            {{ __('Print') }}
                        </x-filament::button>
                        <x-filament::button
                            wire:click="mountAction('collectPayment', { invoice_id: '{{ $selectedInvoice['id'] }}' })"
                            color="success"
                            icon="heroicon-m-currency-dollar"
                        >
                            {{ __('Collect payment') }}
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @else
                <div class="flex items-center justify-center h-64 text-gray-500">
                    <div class="text-center">
                        <x-filament::icon name="heroicon-o-currency-dollar" class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-2">{{ __('Select an invoice from the queue to begin collecting payment.') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
