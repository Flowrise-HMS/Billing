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
        <x-slot name="heading">{{ __('Refunds') }}</x-slot>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('Date') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Patient') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Amount') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Reason') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Method') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Gateway') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Recorded by') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getRefunds() as $row)
                        <tr class="divide-x divide-gray-200 dark:divide-gray-700">
                            <td class="px-3 py-2">{{ $row['received_at'] }}</td>
                            <td class="px-3 py-2">{{ $row['patient_name'] }} <span class="text-gray-500">({{ $row['mrn'] }})</span></td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $row['amount'], 2) }}</td>
                            <td class="px-3 py-2">{{ $row['reason'] ?? __('N/A') }}</td>
                            <td class="px-3 py-2">{{ $row['method'] }}</td>
                            <td class="px-3 py-2">{{ $row['gateway'] }}</td>
                            <td class="px-3 py-2">{{ $row['recorded_by'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-center text-gray-500">{{ __('No refunds recorded.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Write-offs') }}</x-slot>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('Date') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Patient') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Amount') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Reason') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Recorded by') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getWriteOffs() as $row)
                        <tr class="divide-x divide-gray-200 dark:divide-gray-700">
                            <td class="px-3 py-2">{{ $row['received_at'] }}</td>
                            <td class="px-3 py-2">{{ $row['patient_name'] }} <span class="text-gray-500">({{ $row['mrn'] }})</span></td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $row['amount'], 2) }}</td>
                            <td class="px-3 py-2">{{ $row['reason'] ?? __('N/A') }}</td>
                            <td class="px-3 py-2">{{ $row['recorded_by'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-gray-500">{{ __('No write-offs recorded.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
