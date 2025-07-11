<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModulResource\Pages;
use App\Filament\Resources\ModulResource\RelationManagers;
use App\Models\modul;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Get;
use Filament\Forms\Components\View;
use Filament\Forms\Components\Repeater;
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
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Placeholder;

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
                    ->disabled()
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
                    ->maxLength(100),
                Repeater::make('modulImage')
                    ->label('Gambar Modul')
                    ->relationship()
                    ->schema([
                        TextInput::make('url')
                            ->label('URL Gambar dari Google')
                            ->live(onBlur: true) // <-- Membuat form bereaksi saat input ini berubah
                            ->required()
                            ->url(),
                        Placeholder::make('image_preview')
                            ->label('Preview Gambar')
                            ->visible(fn(Get $get): bool => filled($get('url')))
                            ->content(function (Get $get): ?HtmlString {
                                $url = $get('url');
                                if (!$url) {
                                    return null;
                                }

                                // Kita membuat tag <img> secara langsung di sini
                                return new HtmlString('<img src="' . e($url) . '" style="max-height: 250px; width: auto; margin-top: 10px;" class="rounded-lg border" />');
                            }),
                    ])
                    ->maxItems(5)
                    ->minItems(1)
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make('article')
                    ->label('Artikel')
                    ->relationship()
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul Artikel')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('link')
                            ->label('Link Artikel')
                            ->required()
                            ->url(),
                        Textarea::make('snippet')
                            ->label('Cuplikan Artikel')
                            ->required()
                            ->maxLength(65535)
                            ->rows(3),
                        TextInput::make('category')
                            ->label('Kategori Artikel')
                            ->required()
                            ->maxLength(100),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make('video')
                    ->label('Video')
                    ->relationship()
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul Video')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('link')
                            ->label('Link Video')
                            ->required()
                            ->url(),
                        Textarea::make('description')
                            ->label('Deskripsi Video')
                            ->required()
                            ->maxLength(65535)
                            ->rows(3),
                    ])
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
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function (modul $record) {
                        $record->delete();
                        return redirect(ModulResource::getUrl('index'));
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
            'index' => Pages\ListModuls::route('/'),
            'create' => Pages\CreateModul::route('/create'),
            'edit' => Pages\EditModul::route('/{record}/edit'),
        ];
    }
}
