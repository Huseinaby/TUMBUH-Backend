<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SellerResource\Pages;
use App\Filament\Resources\SellerResource\RelationManagers;
use App\Models\SellerDetail;
use App\Models\User;
use Dom\Text;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SellerResource extends Resource
{
    protected static ?string $model = SellerDetail::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Detail Penjual';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->label('User')
                    ->options(User::whereDoesntHave('sellerDetail')
                        ->pluck('username', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(2)
                    ->placeholder('Pilih user'),
                TextInput::make('store_name')
                    ->label('Nama Toko')
                    ->required()
                    ->maxLength(100),
                TextInput::make('store_phone')
                    ->label('Telepon Toko')
                    ->required()
                    ->maxLength(15)
                    ->tel()
                    ->placeholder('08123456789'),
                TextArea::make('store_description')
                    ->label('Deskripsi Toko')
                    ->required()
                    ->maxLength(500)
                    ->rows(3),
                TextArea::make('store_address')
                    ->label('Alamat Toko')
                    ->required()
                    ->maxLength(150)
                    ->rows(2),
                FileUpload::make('store_logo')
                    ->label('Logo Toko')
                    ->image()
                    ->openable()
                    ->downloadable()
                    ->required()
                    ->maxSize(1024) // 1MB
                    ->disk('public')
                    ->directory('store_logos')
                    ->preserveFilenames(),
                FileUpload::make('store_banner')
                    ->label('Banner Toko')
                    ->image()
                    ->required()
                    ->openable()
                    ->downloadable()
                    ->maxSize(2048) // 2MB
                    ->disk('public')
                    ->directory('store_banners')
                    ->preserveFilenames(),
                TextInput::make('bank_name')
                    ->label('Nama Bank')
                    ->maxLength(50)
                    ->placeholder('Bank Central Asia'),
                TextInput::make('bank_account_number')
                    ->label('Nomor Rekening Bank')
                    ->maxLength(20)
                    ->placeholder('1234567890'),
                TextInput::make('bank_account_holder_name')
                    ->label('Nama Pemilik Rekening')
                    ->maxLength(50)
                    ->placeholder('John Doe'),
                TextInput::make('nomor_induk_kependudukan')
                    ->label('NIK')
                    ->maxLength(16)
                    ->placeholder('1234567890123456'),
                FileUpload::make('foto_ktp')
                    ->label('Foto KTP')
                    ->image()
                    ->columnSpan(2)
                    ->required()
                    ->maxSize(2048) // 2MB
                    ->disk('public')
                    ->directory('ktp_photos')
                    ->preserveFilenames(),
                Select::make('status')
                    ->label('Status')
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
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.username')
                    ->label('Username')
                    ->searchable(),
                TextColumn::make('store_name')
                    ->label('Nama Toko')
                    ->searchable(),
                ImageColumn::make('store_logo')
                    ->label('Logo Toko')
                    ->circular(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListSellers::route('/'),
            'create' => Pages\CreateSeller::route('/create'),
            'edit' => Pages\EditSeller::route('/{record}/edit'),
        ];
    }
}
