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
                    ->default('user')
                    ->options([
                        'user' => 'User',
                        'admin' => 'Admin',
                        'seller' => 'Seller',
                        'moderator' => 'Moderator',
                    ]),
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
                //
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
