<?php

namespace Modules\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum InvoiceType: string implements HasColor, HasDescription, HasLabel
{
    case Standalone = 'standalone';
    case Interim = 'interim';
    case Final = 'final';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Standalone => __('Standalone'),
            self::Interim => __('Interim'),
            self::Final => __('Final'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Standalone => __('Single invoice not tied to an admission episode.'),
            self::Interim => __('Partial billing during an ongoing episode of care.'),
            self::Final => __('Closing invoice after services are complete.'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Standalone => 'gray',
            self::Interim => 'warning',
            self::Final => 'success',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
