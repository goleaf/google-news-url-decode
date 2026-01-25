<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ArticleStatsOverview extends BaseWidget
{
    public static function canView(): bool
    {
        return true;
    }

    protected function getStats(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('article_stats', 60, function () {
            return [
                Stat::make('Total Articles', Article::count())
                    ->description('All decoded news')
                    ->descriptionIcon('heroicon-m-newspaper')
                    ->color('sky'),
                Stat::make('Saved Today', Article::whereDate('created_at', today())->count())
                    ->description('New articles processed')
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->color('emerald'),
                Stat::make('Sources', Article::distinct('source_name')->count('source_name'))
                    ->description('Active news domains')
                    ->descriptionIcon('heroicon-m-globe-alt')
                    ->color('indigo'),
            ];
        });
    }
}
