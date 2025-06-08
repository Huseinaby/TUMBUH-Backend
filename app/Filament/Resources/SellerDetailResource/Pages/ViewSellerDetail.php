<?php

namespace App\Filament\Resources\SellerDetailResource\Pages;

use App\Filament\Resources\SellerDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSellerDetail extends ViewRecord
{
    protected static string $resource = SellerDetailResource::class;

    public function getViewData(): array
    {
        return [
            'record' => $this->getRecord(),
        ];
    }
}
