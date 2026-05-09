<?php

namespace Modules\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PaymentIntentStatus: string implements HasColor, HasDescription, HasLabel
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Succeeded => __('Succeeded'),
            self::Failed => __('Failed'),
            self::Expired => __('Expired'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Pending => __('Payment is in progress or awaiting customer action.'),
            self::Succeeded => __('Funds were captured successfully.'),
            self::Failed => __('The payment attempt failed; no funds were taken.'),
            self::Expired => __('The payment session expired before completion.'),
            self::Cancelled => __('The payment was cancelled before capture.'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Succeeded => 'success',
            self::Failed => 'danger',
            self::Expired => 'gray',
            self::Cancelled => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
