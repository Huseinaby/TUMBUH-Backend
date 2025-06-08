<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SellerDetailResource\Pages;
use App\Filament\Resources\SellerDetailResource\RelationManagers;
use App\Models\SellerDetail;
use Dom\Text;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;


class SellerDetailResource extends Resource
{
    protected static ?string $model = SellerDetail::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('user_id')->disabled(),
                TextInput::make('store_name')->required()->maxLength(100),
                TextArea::make('store_description')->required(),
                TextInput::make('origin_id')->required(),
                TextInput::make('store_address')->required()->maxLength(150),
                TextInput::make('store_phone')->required()->maxLength(15),
                FileUpload::make('store_logo')->image(),
                FileUpload::make('store_banner')->image(),
                TextInput::make('bank_name')->nullable(),
                TextInput::make('bank_account_number')->nullable(),
                TextInput::make('bank_account_holder_name')->nullable(),
                TextInput::make('nomor_induk_kependudukan')->nullable(),
                FileUpload::make('ktp_image')
                    ->image()
                    ->nullable(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.username')->label('Username'),
                TextColumn::make('store_name'),
                TextColumn::make('store_phone'),
                BadgeColumn::make('status')
                    ->colors([
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state){
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => ucfirst($state),
                    })
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('approve')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        $record->update(['status' => 'approved']);
                        $record->user->update(['role' => 'seller']);
                    }),
                
                Action::make('reject')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        $record->update(['status' => 'rejected']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListSellerDetails::route('/'),
            'create' => Pages\CreateSellerDetail::route('/create'),
            'edit' => Pages\EditSellerDetail::route('/{record}/edit'),
        ];
    }
}
