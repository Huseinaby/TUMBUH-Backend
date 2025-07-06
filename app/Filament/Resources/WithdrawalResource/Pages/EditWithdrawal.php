<?php

namespace App\Filament\Resources\WithdrawalResource\Pages;

use App\Filament\Resources\WithdrawalResource;
use App\Models\WalletHistory;
use App\Models\WithdrawRequest;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\NotificationService;
use Log;

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
            if ($user->sellerDetail && $user->sellerDetail->saldo >= $withdrawal->amount) {
                $user->sellerDetail->decrement('saldo', $withdrawal->amount);
            } else {
                Log::error('Withdrawal approval failed: Insufficient balance for user ID ' . $user->id);
                return;
            }

            $withdrawal->update([
                'approved_at' => now(),
            ]);

            WalletHistory::create([
                'user_id' => $user->id,
                'amount' => -$withdrawal->amount,
                'type' => 'expense',
                'description' => 'Withdrawal approved at ' . now(),
            ]);

            app(NotificationService::class)->sendToUser(
                $user,
                'Withdrawal Approved',
                $withdrawal->note ?? 'Permintaan penarikan dana Anda telah disetujui.', 
                [
                    'type' => 'success',
                    'category' => 'marketplace',
                    'screen' => 'withdrawals',
                ]
            );
        } elseif ($withdrawal->status === 'rejected') {
            $withdrawal->update([
                'rejected_at' => now(),
            ]);

            app(NotificationService::class)->sendToUser(
                $user,
                'Withdrawal Rejected',
                $withdrawal->note ?? 'Permintaan penarikan dana Anda telah ditolak.', 
                [
                    'type' => 'error',
                    'category' => 'marketplace',
                    'screen' => 'withdrawals',
                ]
            );
        }
    }

}
