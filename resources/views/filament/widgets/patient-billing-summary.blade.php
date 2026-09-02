@php
    use Modules\Core\Support\Currency;

    $balance = $this->outstandingBalance;
@endphp

<div>
    @if ($balance !== null)
        <div
            class="flex items-center justify-between gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-m-banknotes" class="h-5 w-5 text-gray-400 dark:text-gray-500" />
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Outstanding balance</span>
            </div>
            <x-filament::badge :color="bccomp($balance, '0', 2) === 1 ? 'danger' : 'gray'" size="lg">
                {{ Currency::format($balance) }}
            </x-filament::badge>
        </div>
    @endif
</div>
