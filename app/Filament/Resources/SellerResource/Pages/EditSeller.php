<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\SellerResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeller extends EditRecord
{
    protected static string $resource = SellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $user = User::where('id', $this->record->user_id);

        if($this->record->status == 'approved') {
            $user->update(['role' => 'seller']);
        } else if($this->record->status == 'rejected') {
            $user->update(['role' => 'user']);
        }   
    }
}
