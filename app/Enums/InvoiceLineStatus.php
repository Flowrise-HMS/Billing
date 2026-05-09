<?php

namespace Modules\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum InvoiceLineStatus: string implements HasColor, HasDescription, HasLabel
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Void = 'void';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::Partial => __('Partial'),
            self::Paid => __('Paid'),
            self::Void => __('Void'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Unpaid => __('No payment has been applied to this line.'),
            self::Partial => __('A payment has been applied; balance remains on this line.'),
            self::Paid => __('This line is fully paid.'),
            self::Void => __('This line was removed from billing.'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Partial => 'info',
            self::Paid => 'success',
            self::Void => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
