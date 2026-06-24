<?php

namespace Modules\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PaymentType: string implements HasColor, HasDescription, HasLabel
{
    case Payment = 'payment';
    case WriteOff = 'write_off';
    case Refund = 'refund';
    case Deposit = 'deposit';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Payment => __('Payment'),
            self::WriteOff => __('Write-off'),
            self::Refund => __('Refund'),
            self::Deposit => __('Deposit'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Payment => __('Real money received from a patient or payer.'),
            self::WriteOff => __('Balance adjusted to zero; no real money exchanged.'),
            self::Refund => __('Money returned to a patient or payer.'),
            self::Deposit => __('Prepayment not yet applied to an invoice.'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Payment => 'success',
            self::WriteOff => 'gray',
            self::Refund => 'danger',
            self::Deposit => 'info',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
