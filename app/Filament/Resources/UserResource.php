<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Users';
    protected static ?string $navigationLabel = 'Users';
    protected static ?string $label = 'User';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('username')
                    ->label('Name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('role')
                    ->label('Role')
                    ->required()
                    ->maxLength(50)
                    ->columnSpanFull()
                    ->default('user'),
                TextInput::make('email_verified_at')
                    ->label('Email Verified At')
                    ->dateTime()
                    ->nullable()
                    ->columnSpanFull(),
                TextInput::make('photo')
                    ->label('Photo URL')
                    ->url()
                    ->nullable()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Placeholder::make('photo')
                    ->label('Photo Preview')
                    ->visible(fn(Get $get): bool => filled($get('photo')))
                    ->content(function (Get $get): ?HtmlString {
                        $url = $get('photo');
                        if (!$url) {
                            return null;
                        }

                        // Kita membuat tag <img> secara langsung di sini
                        return new HtmlString('<img src="' . e($url) . '" style="max-height: 250px; width: auto; margin-top: 10px;" class="rounded-lg border" />');
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('Photo')
                    ->circular()
                    ->size(50)
                    ->defaultIcon('heroicon-o-user-circle'),
                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->icon('heroicon-o-envelope')
                    ->iconColor('primary')
                    ->copyable()
                    ->copyMessage('Email telah disalin ke clipboard')
                    ->copyMessageDuration(1500)
                    ->searchable(), 
                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'admin' => 'primary',
                        'seller' => 'success',
                        'moderator' => 'warning',
                        default => 'secondary',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->label('Email Verified At')
                    ->dateTime()
                    ->sortable()
                    ->searchable(),
                
                
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
