<?php

namespace App\Filament\Resources\MealPlanTemplates\Pages;

use App\Filament\Resources\MealPlanTemplates\MealPlanTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMealPlanTemplates extends ManageRecords
{
    protected static string $resource = MealPlanTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
