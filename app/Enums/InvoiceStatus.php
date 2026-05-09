<?php

namespace Modules\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum InvoiceStatus: string implements HasColor, HasDescription, HasLabel
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Issued => __('Issued'),
            self::PartiallyPaid => __('Partially paid'),
            self::Paid => __('Paid'),
            self::Void => __('Void'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Draft => __('Invoice is being prepared and is not yet sent to the patient.'),
            self::Issued => __('Invoice has been issued and is awaiting payment.'),
            self::PartiallyPaid => __('Some lines or amounts have been paid; balance remains.'),
            self::Paid => __('Invoice is fully settled.'),
            self::Void => __('Invoice was voided and should not be collected.'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'info',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Void => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
