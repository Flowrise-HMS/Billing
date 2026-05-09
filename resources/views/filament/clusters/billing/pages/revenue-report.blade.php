<x-filament-panels::page>
    <form method="GET" class="grid gap-4 md:grid-cols-4">
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
        <div class="flex items-end gap-2">
            <x-filament::button type="submit">{{ __('Apply') }}</x-filament::button>
            <x-filament::button
                tag="a"
                color="gray"
                href="{{ route('billing.reports.revenue.csv', ['start_date' => $this->startDate, 'end_date' => $this->endDate, 'branch_id' => $this->branchId]) }}"
            >
                {{ __('Export CSV') }}
            </x-filament::button>
        </div>
    </form>

    <div class="grid gap-4 md:grid-cols-3 mt-6">
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Billed') }}</div>
            <div class="text-2xl font-semibold">{{ number_format((float) data_get($this->report, 'summary.billed', 0), 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Collected') }}</div>
            <div class="text-2xl font-semibold">{{ number_format((float) data_get($this->report, 'summary.collected', 0), 2) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('Outstanding') }}</div>
            <div class="text-2xl font-semibold">{{ number_format((float) data_get($this->report, 'summary.outstanding', 0), 2) }}</div>
        </x-filament::section>
    </div>

    <x-filament::section class="mt-6">
        <x-slot name="heading">{{ __('Collections by branch') }}</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-2">{{ __('Branch') }}</th>
                        <th class="py-2">{{ __('Collected') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (data_get($this->report, 'branch_breakdown', []) as $row)
                        <tr class="border-b">
                            <td class="py-2">{{ $row['branch_name'] }}</td>
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
</x-filament-panels::page>

