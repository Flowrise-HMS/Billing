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

    <x-filament-widgets::widgets
        :widgets="$this->getTillReconciliationWidgets()"
        :columns="1"
    />
</x-filament-panels::page>
