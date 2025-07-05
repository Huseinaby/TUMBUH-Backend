<?php

namespace App\Filament\Resources\WithdrawalResource\Pages;

use App\Filament\Resources\WithdrawalResource;
use App\Models\WithdrawRequest;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\NotificationService;

class EditWithdrawal extends EditRecord
{
    protected static string $resource = WithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function afterSave(): void
    {
        $withdrawal = $this->record;
        $user = $withdrawal->user;

        if ($withdrawal->status === 'approved') {
            $withdrawal->user->sellerDetail->decrement('saldo', $withdrawal->amount);
            $withdrawal->approved_at = now();
            $withdrawal->save();

            app(NotificationService::class)->sendToUser(
                $user,
                'Withdrawal Approved',
                $withdrawal->note,
                [
                    'type' => 'success',
                    'category' => 'marketplace',
                    'screen' => 'withdrawals',
                ]
            );
        } elseif ($withdrawal->status === 'rejected') {
            $withdrawal->rejected_at = now();
            $withdrawal->save();
            app(NotificationService::class)->sendToUser(
                $user,
                'Withdrawal Rejected',
                $withdrawal->note,
                [
                    'type' => 'error',
                    'category' => 'marketplace',
                    'screen' => 'withdrawals',
                ]
            );
        }
    }
}
