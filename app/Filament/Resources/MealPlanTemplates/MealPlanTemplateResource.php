<?php

namespace App\Filament\Resources\MealPlanTemplates;

use App\Filament\Resources\MealPlanTemplates\Pages\ManageMealPlanTemplates;
use App\Models\MealPlanTemplate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class MealPlanTemplateResource extends Resource
{
    protected static ?string $model = MealPlanTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Nutrition';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            Textarea::make('description')->rows(3)->columnSpanFull(),
            Toggle::make('is_active')->default(true),
            Select::make('created_by')
                ->relationship('creator', 'name')
                ->searchable()
                ->preload()
                ->default(fn () => auth()->id()),
            Repeater::make('mealOptions')
                ->relationship()
                ->schema([
                    Select::make('meal_type')
                        ->options([
                            'shots' => 'Shots',
                            'breakfast' => 'Breakfast',
                            'lunch' => 'Lunch',
                            'dinner' => 'Dinner',
                        ])
                        ->required(),
                    Select::make('category_slug')
                        ->label('Public Category')
                        ->options([
                            'shots' => 'Shots',
                            'breakfast' => 'Breakfast',
                            'lunch' => 'Lunch',
                            'dinner' => 'Dinner',
                        ]),
                    TextInput::make('title')->required()->maxLength(255),
                    TextInput::make('slug')
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('tag')->maxLength(80),
                    Textarea::make('description')->rows(2),
                    TextInput::make('calories')->numeric(),
                    TextInput::make('price')->numeric()->prefix('INR'),
                    TextInput::make('protein')->numeric()->suffix('g'),
                    TextInput::make('carbs')->numeric()->suffix('g'),
                    TextInput::make('fat')->numeric()->suffix('g'),
                    TagsInput::make('ingredients'),
                    TextInput::make('sort_order')->numeric()->default(0),
                    Toggle::make('is_default'),
                    Toggle::make('is_active')->default(true),
                ])
                ->columnSpanFull()
                ->defaultItems(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('creator.name')->label('Created by')->toggleable(),
                TextColumn::make('meal_options_count')->counts('mealOptions')->label('Options'),
                TextColumn::make('updated_at')->since(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMealPlanTemplates::route('/'),
        ];
    }
}
