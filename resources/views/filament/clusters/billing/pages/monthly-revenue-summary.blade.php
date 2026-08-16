<x-filament-panels::page>
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <input type="month" wire:model="month" wire:change="loadSummary"
               class="rounded-lg border-gray-300 dark:border-gray-600">
        <select wire:model="branchId" wire:change="loadSummary"
                class="rounded-lg border-gray-300 dark:border-gray-600">
            @foreach ($this->branches as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
        <x-filament::button wire:click="loadSummary">{{ __('Run summary') }}</x-filament::button>
        <x-filament::button wire:click="exportCsv" color="gray">{{ __('CSV') }}</x-filament::button>
        <x-filament::button wire:click="exportPdf" color="gray">{{ __('PDF') }}</x-filament::button>
    </div>

    @if ($summary)
        <x-filament::section>
            <x-slot name="heading">{{ __('Revenue by payment method') }}</x-slot>
            <ul>
                @foreach ($summary['revenue_by_method'] as $method => $amount)
                    <li class="flex justify-between">
                        <span>{{ ucfirst($method) }}</span>
                        <span>{{ number_format((float) $amount, 2) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('Summary') }}</x-slot>
            <ul>
                <li class="flex justify-between"><span>{{ __('Revenue total') }}</span><span>{{ number_format((float) ($summary['revenue_total'] ?? 0), 2) }}</span></li>
                <li class="flex justify-between"><span>{{ __('Refunds') }}</span><span>{{ number_format((float) ($summary['refunds_total'] ?? 0), 2) }}</span></li>
                <li class="flex justify-between"><span>{{ __('Net revenue') }}</span><span>{{ number_format((float) ($summary['net_revenue'] ?? 0), 2) }}</span></li>
            </ul>
        </x-filament::section>

        {{-- REQUIRED footnote (spec §3.2): applied-deposit revenue lands in the gateway bucket --}}
        <x-filament::section>
            <p class="text-xs text-gray-500">
                {{ __('The gateway bucket includes revenue from applied deposits (type=Payment, gateway=deposit), not only live gateway collections.') }}
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
