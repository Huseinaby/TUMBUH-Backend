<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModulResource\Pages;
use App\Filament\Resources\ModulResource\RelationManagers;
use App\Models\Modul;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ModulResource extends Resource
{
    protected static ?string $model = modul::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $label = 'Modul';
    protected static ?string $navigationLabel = 'Modul';
    protected static ?string $navigationGroup = 'Edukasi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('Judul Modul')
                    ->required()
                    ->maxLength(255),
                Select::make('user_id')
                    ->label('Pengguna')
                    ->relationship('user', 'username')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih pengguna'),
                Textarea::make('content')
                    ->label('Konten Modul')
                    ->required()
                    ->maxLength(65535)
                    ->rows(10)
                    ->columnSpanFull(),
                TextInput::make('category')
                    ->label('Kategori Modul')
                    ->required()
                    ->maxLength(100)
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
                TextColumn::make('title')
                    ->label('Judul Modul')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.username')
                    ->label('Pengguna')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('category')
                    ->label('Kategori Modul')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable(),
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
            'index' => Pages\ListModuls::route('/'),
            'create' => Pages\CreateModul::route('/create'),
            'edit' => Pages\EditModul::route('/{record}/edit'),
        ];
    }
}
