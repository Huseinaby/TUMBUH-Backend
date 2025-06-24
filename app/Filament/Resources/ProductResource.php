<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Repeater;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $label = 'Daftrar Produk';
    protected static ?string $navigationLabel = 'Produk';
    protected static ?string $navigationGroup = 'Marketplace';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->label('Penjual')
                    ->options(User::whereHas('sellerDetail')
                        ->pluck('username', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(2)
                    ->placeholder('Pilih penjual'),
                TextInput::make('name')
                    ->label('Nama Produk')
                    ->required()
                    ->maxLength(100),
                Select::make('product_category_id')
                    ->label('Kategori Produk')
                    ->relationship('productCategories', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih kategori produk'),
                TextInput::make('description')
                    ->label('Deskripsi Produk')
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('Harga')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->placeholder('Masukkan harga produk'),
                TextInput::make('weight')
                    ->label('Berat (gram)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->placeholder('Masukkan berat produk'),
                TextInput::make('stock')
                    ->label('Stok')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->placeholder('Masukkan jumlah stok produk'),
                Repeater::make('images')
                    ->label('Gambar Produk')
                    ->relationship()
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Gambar')
                            ->image()
                            ->openable()
                            ->disk('public')
                            ->directory('products')
                            ->preserveFilenames()
                            ->maxSize(1024)
                            ->columnSpanFull(),
                    ])
                    ->maxItems(4)
                    ->minItems(1)
                    ->addActionLabel('Tambah Gambar')
                    ->collapsible()
                    ->columnSpanFull(),
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
                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.username')
                    ->label('Penjual')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('productCategories.name')
                    ->label('Kategori')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('price')
                    ->label('Harga')
                    ->sortable()
                    ->money('idr', true)
                    ->searchable(),
                TextColumn::make('stock')
                    ->label('Stok')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->searchable(),                
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        $record->delete();
                        return redirect()->route('filament.resources.products.index');
                    })->icon('heroicon-o-trash'),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
