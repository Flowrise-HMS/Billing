<?php

namespace Modules\Billing\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Modules\Billing\Models\Invoice;

class InvoiceExporter extends Exporter
{
    protected static ?string $model = Invoice::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('invoice_number'),
            ExportColumn::make('patient.mrn'),
            ExportColumn::make('patient.first_name'),
            ExportColumn::make('patient.last_name'),
            ExportColumn::make('guest_name'),
            ExportColumn::make('status'),
            ExportColumn::make('invoice_type'),
            ExportColumn::make('currency'),
            ExportColumn::make('subtotal'),
            ExportColumn::make('tax_total'),
            ExportColumn::make('discount_total'),
            ExportColumn::make('total'),
            ExportColumn::make('amount_paid'),
            ExportColumn::make('issued_at'),
            ExportColumn::make('due_at'),
            ExportColumn::make('branch.name'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your invoice export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
