<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\SellerResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Events\UserNotification;

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
            broadcast(new UserNotification(
                $this->record->user_id,
                'Permina Anda sebagai penjual telah disetujui.',
                'success'
            ))->toOthers();
        } else if($this->record->status == 'rejected') {
            $user->update(['role' => 'user']);
            broadcast(new UserNotification(
                $this->record->user_id,
                'Permohonan Anda sebagai penjual telah ditolak.',
                'error'
            ))->toOthers();
        }   
    }
}
