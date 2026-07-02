<?php

namespace App\Filament\Doctor\Resources\Consultations\Pages;

use App\Filament\Doctor\Resources\Consultations\ConsultationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageConsultations extends ManageRecords
{
    protected static string $resource = ConsultationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
