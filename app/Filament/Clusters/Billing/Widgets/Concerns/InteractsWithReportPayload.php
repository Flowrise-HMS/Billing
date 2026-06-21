<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns;

use Filament\Support\ArrayRecord;
use Livewire\Attributes\Reactive;

trait InteractsWithReportPayload
{
    #[Reactive]
    public ?array $reportPayload = null;

    /**
     * @return list<array<string, mixed>>
     */
    protected function reportRows(string $key, string $rowKey = '__key'): array
    {
        $rows = $this->reportPayload[$key] ?? [];

        return collect($rows)
            ->values()
            ->map(function (array $row, int $index) use ($rowKey): array {
                $row[ArrayRecord::getKeyName()] = (string) ($row[$rowKey] ?? $index);

                return $row;
            })
            ->all();
    }
}
