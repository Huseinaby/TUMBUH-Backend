<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawalResource\Pages;
use App\Filament\Resources\WithdrawalResource\RelationManagers;
use App\Models\WithdrawRequest;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WithdrawalResource extends Resource
{
    protected static ?string $model = WithdrawRequest::class;
    protected static ?string $label = 'Withdrawal Request';
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Withdrawals';

    protected static ?string $navigationGroup = 'Marketplace';



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->label('User')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload()
                    ->disabled(),
                TextInput::make('amount')
                    ->label('Amount')
                    ->required()
                    ->numeric()
                    ->minValue(10000)
                    ->maxValue(1000000000)
                    ->placeholder('Enter amount'),
                TextInput::make('bank_name')
                    ->label('Bank Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('account_number')
                    ->label('Account Number')
                    ->required()
                    ->maxLength(255),
                TextInput::make('account_name')
                    ->label('Account Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('note')
                    ->label('Note')
                    ->maxLength(500)
                    ->placeholder('Optional note for the withdrawal request'),
                Select::make('status')
                    ->label('Status')
                    ->default('pending')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                FileUpload::make('proof_transfer')
                    ->label('Proof of Transfer')
                    ->image()
                    ->required()
                    ->maxSize(1024) // 1MB
                    ->disk('public')
                    ->directory('withdrawal_proofs')
                    ->placeholder('Upload proof of transfer'),
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
                    ->label('User')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('IDR')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'rejected' => 'danger',
                        'pending' => 'warning',
                        'approved' => 'success',
                    }),
                TextColumn::make('created_at')
                    ->label('Created At')
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
            'index' => Pages\ListWithdrawals::route('/'),
            'create' => Pages\CreateWithdrawal::route('/create'),
            'edit' => Pages\EditWithdrawal::route('/{record}/edit'),
        ];
    }
}
