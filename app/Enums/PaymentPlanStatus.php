<?php

namespace Modules\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PaymentPlanStatus: string implements HasColor, HasDescription, HasLabel
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Defaulted = 'defaulted';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
            self::Defaulted => __('Defaulted'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Active => __('Installments are being collected on schedule.'),
            self::Completed => __('All installments have been fully paid.'),
            self::Cancelled => __('The payment plan was cancelled.'),
            self::Defaulted => __('The patient has defaulted on this plan.'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'info',
            self::Completed => 'success',
            self::Cancelled => 'gray',
            self::Defaulted => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
