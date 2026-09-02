<?php

namespace Modules\Billing\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Modules\Billing\Models\Payment;

class PaymentExporter extends Exporter
{
    protected static ?string $model = Payment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('patient.mrn'),
            ExportColumn::make('patient.first_name'),
            ExportColumn::make('patient.last_name'),
            ExportColumn::make('method'),
            ExportColumn::make('gateway'),
            ExportColumn::make('type'),
            ExportColumn::make('amount'),
            ExportColumn::make('currency'),
            ExportColumn::make('provider_transaction_id'),
            ExportColumn::make('received_at'),
            ExportColumn::make('recorder.name'),
            ExportColumn::make('branch.name'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your payment export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
