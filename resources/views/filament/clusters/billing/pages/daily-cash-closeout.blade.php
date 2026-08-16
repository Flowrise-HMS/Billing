<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-3">
            <input type="date" wire:model="summaryDate" wire:change="loadCloseout"
                   class="rounded-lg border-gray-300 dark:border-gray-600">
            <select wire:model="branchId" wire:change="loadCloseout"
                    class="rounded-lg border-gray-300 dark:border-gray-600">
                @foreach ($this->branches as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <input type="number" step="0.01" min="0" wire:model="openingCash" wire:change="loadCloseout"
                   placeholder="{{ __('Opening cash') }}"
                   class="rounded-lg border-gray-300 dark:border-gray-600">
        </div>
        <div class="flex gap-3">
            <x-filament::button wire:click="exportCsv" color="gray">
                {{ __('CSV') }}
            </x-filament::button>
            <x-filament::button wire:click="exportPdf" color="gray">
                {{ __('PDF') }}
            </x-filament::button>
        </div>
    </div>

    @if ($staleCount > 0)
        <x-filament::section color="warning">
            <x-slot name="heading">{{ __('Summary may be out of date') }}</x-slot>
            {{ __('Cash-affecting transactions were recorded after this summary was finalized. Review before relying on these totals.') }}
        </x-filament::section>
    @endif

    <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
        @foreach (['net_revenue' => __('Net revenue'), 'tax_collected' => __('Tax collected'), 'refunds_total' => __('Refunds'), 'deposits_received' => __('Deposits received'), 'cash_in' => __('Cash in')] as $key => $label)
            <x-filament::section>
                <x-slot name="heading">{{ $label }}</x-slot>
                {{ number_format((float) ($closeout[$key] ?? 0), 2) }}
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section>
        <x-slot name="heading">{{ __('Till reconciliation') }}</x-slot>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('Cashier') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Opening') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Cash-in') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Cash refunds') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Change given') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Expected closing') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Counted closing') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Variance') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Status') }}</th>
                        <th class="px-3 py-2 text-left"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cashiers as $cashierId => $figures)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="px-3 py-2">{{ $figures['cashier_name'] }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $figures['opening_cash'], 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $figures['cash_in'], 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $figures['cash_refunds'], 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $figures['change_given'], 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $figures['expected_closing'], 2) }}</td>
                            <td class="px-3 py-2 text-right">
                                <input type="number" step="0.01" min="0" wire:model="countedClosing"
                                       class="w-32 rounded-lg border-gray-300 dark:border-gray-600">
                            </td>
                            <td class="px-3 py-2 text-right">{{ $figures['variance'] ?? '0.00' }}</td>
                            <td class="px-3 py-2">
                                <x-filament::badge :color="($figures['status'] ?? 'open') === 'finalized' ? 'success' : 'gray'">
                                    {{ $figures['status'] ?? 'open' }}
                                </x-filament::badge>
                            </td>
                            <td class="px-3 py-2">
                                @if (($figures['status'] ?? 'open') === 'finalized')
                                    <x-filament::button wire:click="reopenCashier" color="warning" size="sm">
                                        {{ __('Reopen') }}
                                    </x-filament::button>
                                @else
                                    <x-filament::button wire:click="finalizeCashier" color="success" size="sm">
                                        {{ __('Finalize') }}
                                    </x-filament::button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
