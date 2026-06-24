<?php

namespace Modules\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PaymentPlanInstallmentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Partial = 'partial';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Paid => __('Paid'),
            self::Overdue => __('Overdue'),
            self::Partial => __('Partial'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Paid => 'success',
            self::Overdue => 'danger',
            self::Partial => 'warning',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
