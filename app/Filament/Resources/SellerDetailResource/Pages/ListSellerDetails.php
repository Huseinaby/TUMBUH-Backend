<?php

namespace App\Filament\Resources\SellerDetailResource\Pages;

use App\Filament\Resources\SellerDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSellerDetails extends ListRecords
{
    protected static string $resource = SellerDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
