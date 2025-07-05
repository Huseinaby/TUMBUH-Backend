<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\SellerResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\NotificationService;


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
        $sellerDetail = $this->record;
        if ($sellerDetail->status === 'approved') {
            $user = User::find($this->record->user_id);

            if ($user) {
                app(NotificationService::class)->sendToUser(
                    $user,
                    'Akun Penjual Telah Diverifikasi',
                    'Selamat! Akun penjual Anda telah diverifikasi dan Anda dapat mulai berjualan.',                
                    [
                        'type' => 'success',
                        'category' => 'marketplace',
                        'screen' => 'dashboard', 
                    ]
                );
            }
        } elseif ($sellerDetail->status === 'rejected') {
            $user = User::find($this->record->user_id);

            if ($user) {
                app(NotificationService::class)->sendToUser(
                    $user,
                    'Akun Penjual Ditolak',
                    'Maaf, pengajuan akun penjual Anda ditolak. Silakan periksa kembali informasi yang Anda berikan dan coba lagi.',                    
                    [
                        'type' => 'error',
                        'category' => 'marketplace',
                        'screen' => 'seller-application',
                    ]

                );
            }
        }
    }
}
