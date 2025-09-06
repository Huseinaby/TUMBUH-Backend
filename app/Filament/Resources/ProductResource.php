<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
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
    protected static ?string $label = 'Produk';
    protected static ?string $navigationLabel = 'Produk';
    protected static ?string $navigationGroup = 'Marketplace';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->label('Penjual')
                    ->disabled()
                    ->columnSpan(2)
                    ->relationship('user', 'username'),
                TextInput::make('name')
                    ->label('Nama Produk')
                    ->disabled(),
                Select::make('product_category_id')
                    ->label('Kategori Produk')
                    ->relationship('productCategories', 'name')
                    ->disabled()
                    ->searchable()
                    ->preload(),
                Textarea::make('description')
                    ->label('Deskripsi Produk')
                    ->disabled()
                    ->columnSpanFull()
                    ->rows(10),
                TextInput::make('price')
                    ->label('Harga')
                    ->disabled(),
                TextInput::make('weight')
                    ->label('Berat (gram)')
                    ->disabled(),
                TextInput::make('stock')
                    ->label('Stok')
                    ->disabled(),
                Repeater::make('images')
                    ->label('Gambar Produk')
                    ->relationship()
                    ->disabled()
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Gambar')
                            ->image()
                            ->openable()
                            ->required()
                            ->disk('public')
                            ->directory('products')
                            ->maxSize(1024)
                            ->columnSpanFull(),
                    ])
                    ->maxItems(4)
                    ->minItems(1)
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
                        return redirect(ProductResource::getUrl('index'));
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

    public static function canCreate(): bool
    {
        return false;
    }
}
