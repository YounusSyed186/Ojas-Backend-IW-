<?php

namespace App\Filament\Doctor\Resources\CustomerMealPlans;

use App\Filament\Doctor\Resources\CustomerMealPlans\Pages\ManageCustomerMealPlans;
use App\Models\CustomerMealPlan;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerMealPlanResource extends Resource
{
    protected static ?string $model = CustomerMealPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboard;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->relationship('customer', 'name')->required()->searchable()->preload(),
            Select::make('meal_plan_template_id')->relationship('template', 'name')->required()->searchable()->preload(),
            DatePicker::make('assigned_on')->required()->default(now()),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')->searchable(),
                TextColumn::make('template.name'),
                TextColumn::make('assigned_on')->date(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['assigned_by'] = auth()->id();

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $data['assigned_by'] = auth()->id();

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomerMealPlans::route('/'),
        ];
    }
}
