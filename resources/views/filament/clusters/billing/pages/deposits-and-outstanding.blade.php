<x-filament-panels::page>
    <div class="flex flex-wrap gap-3">
        <select wire:model="branchId" wire:change="$refresh"
                class="rounded-lg border-gray-300 dark:border-gray-600">
            @foreach ($this->branches as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <x-filament::section>
        <x-slot name="heading">{{ __('Patient deposit balances') }}</x-slot>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('Patient') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Deposited') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Applied') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Remaining') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getDepositBalances() as $row)
                        <tr class="divide-x divide-gray-200 dark:divide-gray-700">
                            <td class="px-3 py-2">{{ $row['patient_name'] }} <span class="text-gray-500">({{ $row['mrn'] }})</span></td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $row['deposited'], 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $row['applied'], 2) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ number_format((float) $row['remaining'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-gray-500">{{ __('No deposit balances.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Outstanding receivables') }}</x-slot>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('Patient') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Invoices') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Outstanding') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getOutstanding() as $row)
                        <tr class="divide-x divide-gray-200 dark:divide-gray-700">
                            <td class="px-3 py-2">{{ $row['patient_name'] }} <span class="text-gray-500">({{ $row['mrn'] }})</span></td>
                            <td class="px-3 py-2 text-right">{{ $row['invoice_count'] }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ number_format((float) $row['outstanding'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-4 text-center text-gray-500">{{ __('No outstanding receivables.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
