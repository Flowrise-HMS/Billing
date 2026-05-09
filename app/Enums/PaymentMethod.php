<?php

namespace Modules\Billing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PaymentMethod: string implements HasColor, HasDescription, HasLabel
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case MobileMoney = 'mobile_money';
    case Gateway = 'gateway';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Card => __('Card'),
            self::BankTransfer => __('Bank transfer'),
            self::MobileMoney => __('Mobile money'),
            self::Gateway => __('Payment gateway'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Cash => __('In-person cash collection at the facility.'),
            self::Card => __('Card payment (POS or online).'),
            self::BankTransfer => __('Direct bank or wire transfer.'),
            self::MobileMoney => __('Mobile wallet or USSD payment.'),
            self::Gateway => __('External card or wallet gateway integration.'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Cash => 'gray',
            self::Card => 'primary',
            self::BankTransfer => 'info',
            self::MobileMoney => 'success',
            self::Gateway => 'warning',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
