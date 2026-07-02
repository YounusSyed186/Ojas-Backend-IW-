<?php

namespace App\Filament\Doctor\Resources\Consultations;

use App\Filament\Doctor\Resources\Consultations\Pages\ManageConsultations;
use App\Models\Consultation;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConsultationResource extends Resource
{
    protected static ?string $model = Consultation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->relationship('customer', 'name')->required()->searchable()->preload(),
            Select::make('status')->options([
                'requested' => 'Requested',
                'scheduled' => 'Scheduled',
                'completed' => 'Completed',
            ])->required(),
            DateTimePicker::make('scheduled_for'),
            Textarea::make('doctor_notes')->rows(4)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')->searchable(),
                TextColumn::make('preferred_slot_at')->dateTime('d M Y, h:i A'),
                TextColumn::make('scheduled_for')->dateTime('d M Y, h:i A'),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query): void {
                $query->where('doctor_id', auth()->id())
                    ->orWhereNull('doctor_id');
            });
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        $data['doctor_id'] = auth()->id();

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageConsultations::route('/'),
        ];
    }
}
