<?php

namespace Modules\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum DailyCashSummaryStatus: string implements HasColor, HasDescription, HasLabel
{
    case Open = 'open';
    case Finalized = 'finalized';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Finalized => __('Finalized'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Open => __('Till is still open for the day; totals may change.'),
            self::Finalized => __('Till was closed and locked for the day.'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Open => 'gray',
            self::Finalized => 'success',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
