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

    @if ($this->getReportTableWidgets())
        <div class="grid gap-4 mt-6">
            <x-filament-widgets::widgets
                :widgets="$this->getReportTableWidgets()"
                :columns="1"
            />
        </div>
    @endif
</x-filament-panels::page>
