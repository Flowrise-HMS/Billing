<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;

class InvoiceLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Line items';

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->status !== InvoiceStatus::Draft;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Line'))
                    ->schema([
                        Select::make('service_id')
                            ->label(__('Service'))
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TextInput::make('description')
                            ->maxLength(255),
                        TextInput::make('quantity')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        TextInput::make('unit_price')
                            ->label(__('Unit price'))
                            ->numeric()
                            ->required(),
                        TextInput::make('discount_amount')
                            ->label(__('Discount'))
                            ->numeric()
                            ->default(0),
                        TextInput::make('tax_amount')
                            ->label(__('Tax'))
                            ->numeric()
                            ->default(0),
                        Textarea::make('adjustment_reason')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('description')->searchable(),
                TextColumn::make('quantity'),
                TextColumn::make('unit_price')->numeric(decimalPlaces: 2),
                TextColumn::make('line_total')->numeric(decimalPlaces: 2),
                TextColumn::make('line_status')->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === InvoiceStatus::Draft)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['line_status'] = InvoiceLineStatus::Unpaid->value;
                        $data['amount_paid'] = 0;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === InvoiceStatus::Draft),
                DeleteAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === InvoiceStatus::Draft),
            ]);
    }
}
