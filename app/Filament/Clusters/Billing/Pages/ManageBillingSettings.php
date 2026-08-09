<?php

namespace Modules\Billing\Filament\Clusters\Billing\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Settings\BillingSettings;
use Modules\Core\Enums\NavigationGroup;

class ManageBillingSettings extends SettingsPage
{
    use HasPageShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static string $settings = BillingSettings::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::SETTINGS;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Billing';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Automation'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('auto_issue_on_discharge')
                            ->label(__('Auto-issue invoice on discharge')),
                        Toggle::make('auto_sync_request_items')
                            ->label(__('Auto-sync clinical orders to invoice lines')),
                        Toggle::make('auto_invoice_on_checkin')
                            ->label(__('Auto-invoice on appointment check-in'))
                            ->helperText(__('Creates and issues an invoice for cash appointments when a patient checks in with a linked service.')),
                        Toggle::make('financial_hold_enabled')
                            ->label(__('Enable patient financial hold checks')),
                        Toggle::make('sms_enabled')
                            ->label(__('Enable billing SMS notifications')),
                    ]),
                Section::make(__('Payments'))
                    ->schema([
                        CheckboxList::make('enabled_payment_methods')
                            ->label(__('Allowed payment methods'))
                            ->options([
                                'cash' => __('Cash'),
                                'card' => __('Card'),
                                'bank_transfer' => __('Bank transfer'),
                                'mobile_money' => __('Mobile money'),
                            ])
                            ->columns(2),
                        Select::make('default_payment_method')
                            ->label(__('Default payment method'))
                            ->options([
                                'cash' => __('Cash'),
                                'card' => __('Card'),
                                'bank_transfer' => __('Bank transfer'),
                                'mobile_money' => __('Mobile money'),
                            ])
                            ->required(),
                    ]),
                Section::make(__('Overdue reminders'))
                    ->schema([
                        TextInput::make('overdue_reminder_cooldown_days')
                            ->label(__('Reminder cooldown (days)'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->required()
                            ->helperText(__('Minimum days between unpaid invoice reminders for the same invoice.')),
                    ]),
            ]);
    }
}
