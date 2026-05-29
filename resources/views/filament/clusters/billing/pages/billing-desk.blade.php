<x-filament-panels::page>
    <div class="grid gap-6 md:grid-cols-5">
        {{-- Left panel: Search + Queue --}}
        <div class="md:col-span-2 space-y-4">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search MRN, name, phone, invoice #, or guest phone...') }}"
                />
            </x-filament::input.wrapper>

            <div class="flex gap-2 flex-wrap">
                <x-filament::badge
                    tag="button"
                    wire:click="$set('statusFilter', 'unpaid')"
                    :color="$statusFilter === 'unpaid' ? 'danger' : 'gray'"
                >
                    {{ __('Unpaid') }}
                </x-filament::badge>
                <x-filament::badge
                    tag="button"
                    wire:click="$set('statusFilter', 'all')"
                    :color="$statusFilter === 'all' ? 'primary' : 'gray'"
                >
                    {{ __('All') }}
                </x-filament::badge>
                <x-filament::badge
                    tag="button"
                    wire:click="$set('sourceFilter', 'pharmacy')"
                    :color="$sourceFilter === 'pharmacy' ? 'warning' : 'gray'"
                >
                    {{ __('Pharmacy') }}
                </x-filament::badge>
                <x-filament::badge
                    tag="button"
                    wire:click="$set('sourceFilter', 'encounter')"
                    :color="$sourceFilter === 'encounter' ? 'info' : 'gray'"
                >
                    {{ __('Encounter') }}
                </x-filament::badge>
                @if ($sourceFilter)
                    <x-filament::badge
                        tag="button"
                        wire:click="$set('sourceFilter', null)"
                        color="gray"
                        class="cursor-pointer"
                    >
                        &times; {{ __('Clear filter') }}
                    </x-filament::badge>
                @endif
            </div>

            <div class="space-y-2">
                @forelse ($invoices as $inv)
                    <div
                        wire:click="selectInvoice('{{ $inv['id'] }}')"
                        class="p-3 rounded-lg border cursor-pointer transition-colors
                            {{ $selectedInvoiceId === $inv['id'] ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ $inv['invoice_number'] }}</span>
                            <x-filament::badge>{{ $inv['status_label'] }}</x-filament::badge>
                        </div>
                        <div class="text-sm text-gray-500 mt-1">{{ $inv['patient_name'] }}</div>
                        <div class="flex items-center justify-between mt-1 text-sm">
                            <span class="text-gray-500">{{ $inv['issued_at'] }}</span>
                            <span class="font-semibold {{ (float) $inv['balance_due'] > 0 ? 'text-danger-600' : 'text-success-600' }}">
                                {{ __('Balance') }}: {{ number_format((float) $inv['balance_due'], 2) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500">
                        {{ __('No outstanding invoices found.') }}
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Right panel: Selected invoice details --}}
        <div class="md:col-span-3 space-y-4">
            @if ($selectedInvoice)
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <span>{{ $selectedInvoice['invoice_number'] }}</span>
                            <x-filament::badge>{{ $selectedInvoice['status_label'] }}</x-filament::badge>
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
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left border-b">
                                    <th class="py-2 pr-4">{{ __('Item') }}</th>
                                    <th class="py-2 pr-4">{{ __('Qty') }}</th>
                                    <th class="py-2 pr-4 text-right">{{ __('Total') }}</th>
                                    <th class="py-2 pr-4 text-right">{{ __('Paid') }}</th>
                                    <th class="py-2 text-right">{{ __('Balance') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($selectedInvoice['lines'] as $line)
                                    <tr class="border-b">
                                        <td class="py-2 pr-4">{{ $line['description'] }}</td>
                                        <td class="py-2 pr-4">{{ $line['quantity'] }}</td>
                                        <td class="py-2 pr-4 text-right">{{ number_format((float) ($line['line_total'] ?? 0), 2) }}</td>
                                        <td class="py-2 pr-4 text-right">{{ number_format((float) ($line['amount_paid'] ?? 0), 2) }}</td>
                                        <td class="py-2 text-right font-medium
                                            {{ (float) ($line['line_total'] ?? 0) - (float) ($line['amount_paid'] ?? 0) > 0 ? 'text-danger-600' : 'text-success-600' }}">
                                            {{ number_format(max(0, (float) ($line['line_total'] ?? 0) - (float) ($line['amount_paid'] ?? 0)), 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="py-4 text-center text-gray-500">{{ __('No line items.') }}</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="font-semibold">
                                    <td colspan="2" class="py-2 pr-4">{{ __('Totals') }}</td>
                                    <td class="py-2 pr-4 text-right">{{ number_format((float) $selectedInvoice['total'], 2) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ number_format((float) $selectedInvoice['amount_paid'], 2) }}</td>
                                    <td class="py-2 text-right text-danger-600">{{ number_format((float) $selectedInvoice['balance_due'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <x-filament::button
                            wire:click="mountAction('collectPayment')"
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
