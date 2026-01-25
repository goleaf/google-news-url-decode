<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return true;
    }

    protected static ?string $slug = '/';

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\ArticleStatsOverview::class,
        ];
    }
}
