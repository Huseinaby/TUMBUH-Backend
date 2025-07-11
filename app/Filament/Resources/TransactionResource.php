<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\DeleteAction;

class TransactionResource extends Resource
{
    protected static ?string $model = transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $label = 'Transaksi';
    protected static ?string $navigationLabel = 'Transaksi';
    protected static ?string $navigationGroup = 'Marketplace';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.username')->label('User'),
                TextColumn::make('orderItems.product.user.username')->label('Seller'),
                TextColumn::make('status')->badge(),
                TextColumn::make('total_price')->money('IDR'),
                TextColumn::make('created_at')->label('tanggal')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function (transaction $record) {
                        $record->delete();
                    })->icon('heroicon-o-trash'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(null);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['orderItems.product.user']);
    }

}
