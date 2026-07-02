<?php

namespace App\Filament\Doctor\Resources\CustomerMealPlans\Pages;

use App\Filament\Doctor\Resources\CustomerMealPlans\CustomerMealPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomerMealPlans extends ManageRecords
{
    protected static string $resource = CustomerMealPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
