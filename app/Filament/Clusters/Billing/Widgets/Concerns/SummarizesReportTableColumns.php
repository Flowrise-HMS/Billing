<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns;

use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Core\Support\Currency;

trait SummarizesReportTableColumns
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>|LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginateReportRows(array $rows): array|LengthAwarePaginator
    {
        if (! $this->getTable()->isPaginated()) {
            return $rows;
        }

        $perPage = $this->getTableRecordsPerPage();

        if ($perPage === 'all') {
            return $rows;
        }

        $perPage = (int) $perPage;
        $page = max(1, (int) $this->getTablePage());
        $total = count($rows);

        return new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            $total,
            $perPage,
            $page,
            ['pageName' => $this->getTablePaginationPageName()],
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function visibleTableRecords(): Collection
    {
        $records = $this->getTableRecords();

        if ($records instanceof Paginator || $records instanceof CursorPaginator) {
            return collect($records->items());
        }

        return collect($records);
    }

    protected function sumVisibleTableColumn(string $column, int $scale = 2): string
    {
        $total = '0';

        foreach ($this->visibleTableRecords() as $record) {
            if (! is_array($record)) {
                continue;
            }

            $value = $record[$column] ?? '0';

            if (! is_numeric($value)) {
                continue;
            }

            $total = bcadd($total, (string) $value, $scale);
        }

        return $total;
    }

    protected function reportMoneySumSummarizer(string $column, ?string $currencyColumn = null): Sum
    {
        return Sum::make()
            ->label(__('Total'))
            ->using(fn (): string => $this->sumVisibleTableColumn($column))
            ->money(
                fn (): string => $this->resolveSummaryCurrency($currencyColumn),
                locale: Currency::defaultLocale(),
                decimalPlaces: 2,
            );
    }

    protected function resolveSummaryCurrency(?string $currencyColumn): string
    {
        if ($currencyColumn === null) {
            return Currency::defaultCode();
        }

        $first = $this->visibleTableRecords()->first();

        if (is_array($first) && filled($first[$currencyColumn] ?? null)) {
            return (string) $first[$currencyColumn];
        }

        return Currency::defaultCode();
    }
}
