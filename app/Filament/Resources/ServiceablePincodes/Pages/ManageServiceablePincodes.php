<?php

namespace App\Filament\Resources\ServiceablePincodes\Pages;

use App\Filament\Resources\ServiceablePincodes\ServiceablePincodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageServiceablePincodes extends ManageRecords
{
    protected static string $resource = ServiceablePincodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
