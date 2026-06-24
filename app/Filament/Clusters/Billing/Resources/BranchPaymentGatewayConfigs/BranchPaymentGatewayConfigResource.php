<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\Pages\CreateBranchPaymentGatewayConfig;
use Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\Pages\EditBranchPaymentGatewayConfig;
use Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\Pages\ListBranchPaymentGatewayConfigs;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Core\Classes\Services\BranchService;

class BranchPaymentGatewayConfigResource extends Resource
{
    protected static ?string $model = BranchPaymentGatewayConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $cluster = BillingCluster::class;

    protected static ?string $navigationLabel = 'Gateway settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Gateway'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('branch_id')
                        ->relationship('branch', 'name')
                        ->required()
                        ->searchable()
                        ->default(app(BranchService::class)->getDefaultBranchId()),
                    Select::make('driver')
                        ->options([
                            'paystack' => 'Paystack',
                            'stripe' => 'Stripe',
                            'flutterwave' => 'Flutterwave',
                            'hubtel' => 'Hubtel',
                        ])
                        ->required(),
                    TextInput::make('display_name'),
                    TextInput::make('public_key')->columnSpanFull(),
                    TextInput::make('secret_key')->password()->revealable()->columnSpanFull(),
                    TextInput::make('webhook_secret')->password()->revealable()->columnSpanFull(),
                    Toggle::make('is_enabled')->default(false),
                    Toggle::make('test_mode')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('branch.name')->sortable(),
                TextColumn::make('driver'),
                IconColumn::make('is_enabled')->boolean(),
                IconColumn::make('test_mode')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('driver');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBranchPaymentGatewayConfigs::route('/'),
            'create' => CreateBranchPaymentGatewayConfig::route('/create'),
            'edit' => EditBranchPaymentGatewayConfig::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }
}
