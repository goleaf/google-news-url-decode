<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reparse')
                ->label('Reparse / Live Process')
                ->icon('heroicon-o-cpu-chip')
                ->url(\App\Filament\Pages\NewsProcessor::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}
