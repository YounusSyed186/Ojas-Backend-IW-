<?php

namespace App\Filament\Resources\Consultations;

use App\Filament\Resources\Consultations\Pages\ManageConsultations;
use App\Models\Consultation;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ConsultationResource extends Resource
{
    protected static ?string $model = Consultation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->relationship('customer', 'name')->required()->searchable()->preload(),
            Select::make('doctor_id')->relationship('doctor', 'name')->searchable()->preload(),
            Select::make('consultation_fee_id')->relationship('fee', 'amount')->searchable()->preload(),
            Select::make('status')->options([
                'pending' => 'Pending',
                'requested' => 'Requested',
                'scheduled' => 'Scheduled',
                'completed' => 'Completed',
            ])->required(),
            Select::make('payment_status')->options([
                'pending' => 'Pending',
                'paid' => 'Paid',
            ])->required(),
            DateTimePicker::make('preferred_slot_at'),
            DateTimePicker::make('scheduled_for'),
            Textarea::make('request_notes')->rows(3)->columnSpanFull(),
            Textarea::make('doctor_notes')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('doctor.name')->label('Doctor')->default('Unassigned'),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment_status')->badge(),
                TextColumn::make('preferred_slot_at')->dateTime('d M Y, h:i A'),
            ])
            ->recordActions([
                EditAction::make(),
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
            'index' => ManageConsultations::route('/'),
        ];
    }
}
