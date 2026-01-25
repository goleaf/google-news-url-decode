<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Filament\Resources\ArticleResource\RelationManagers\ParentArticlesRelationManager;
use App\Filament\Resources\ArticleResource\RelationManagers\RelatedArticlesRelationManager;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ArticleResource extends Resource
{
    public static function canAccess(): bool
    {
        return true;
    }

    protected static bool $shouldSkipAuthorization = true;

    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['categories']);
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getModelLabel(): string
    {
        return 'News Article';
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return $record?->title;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'guid', 'original_url', 'source_name'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Source' => $record->source_name,
            'Categories' => $record->categories->pluck('name')->implode(', '),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return \Illuminate\Support\Facades\Cache::remember('article_count', 300, fn() => (string) static::getModel()::count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::count() > 100 ? 'warning' : 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Article Details')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->columnSpanFull()
                            ->prefixIcon('heroicon-m-document-text')
                            ->prefixIconColor('sky')
                            ->placeholder('Enter the article title...'),

                        Forms\Components\Select::make('categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->searchable()
                            ->searchDebounce(500)
                            ->preload()
                            ->prefixIcon('heroicon-m-tag')
                            ->prefixIconColor('emerald')
                            ->placeholder('Select categories...')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-tag')
                                    ->prefixIconColor('emerald')
                                    ->placeholder('Enter category name...'),
                                Forms\Components\Textarea::make('rss_url')
                                    ->label('RSS Feed URL')
                                    ->placeholder('Enter RSS feed URL...'),
                            ])
                            ->required(),


                        Forms\Components\Select::make('sources')
                            ->relationship('sources', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->prefixIcon('heroicon-m-globe-alt')
                            ->prefixIconColor('indigo')
                            ->placeholder('Select Sources'),

                        Forms\Components\TextInput::make('source_name')
                            ->label('Source Name (Override)')
                            ->prefixIcon('heroicon-m-globe-alt')
                            ->prefixIconColor('indigo')
                            ->placeholder('Enter source name...'),

                        Forms\Components\TextInput::make('source_url')
                            ->label('Source URL')
                            ->url()
                            ->prefixIcon('heroicon-m-link')
                            ->prefixIconColor('indigo')
                            ->placeholder('Enter source URL...'),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Published At')
                            ->prefixIcon('heroicon-m-calendar')
                            ->prefixIconColor('sky')
                            ->placeholder('Select publication date...'),

                        Forms\Components\Placeholder::make('guid')
                            ->label('GUID')
                            ->content(fn($record) => $record?->guid ?? '-'),
                    ])->columns(2),

                Forms\Components\Section::make('Links')
                    ->schema([
                        Forms\Components\TextInput::make('original_url')
                            ->label('Original URL')
                            ->columnSpanFull()
                            ->prefixIcon('heroicon-m-link')
                            ->prefixIconColor('slate')
                            ->placeholder('Enter original Google News URL...'),

                        Forms\Components\TextInput::make('decoded_url')
                            ->label('Decoded URL')
                            ->columnSpanFull()
                            ->prefixIcon('heroicon-m-arrow-top-right-on-square')
                            ->prefixIconColor('indigo')
                            ->placeholder('Enter decoded destination URL...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['categories', 'parentArticles', 'sources'])->withCount('relatedArticles'))
            ->deferLoading()
            ->poll('5s')
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-m-document-text')
                    ->iconColor('sky')
                    ->tooltip(fn($record) => $record->title),

                Tables\Columns\TextColumn::make('categories.name')
                    ->badge()
                    ->color('emerald')
                    ->icon('heroicon-m-tag')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('related_articles_count')
                    ->label('Related News')
                    ->badge()
                    ->color('amber')
                    ->icon('heroicon-m-rectangle-stack')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sources.name')
                    ->label('Sources')
                    ->badge()
                    ->color('indigo')
                    ->icon('heroicon-m-globe-alt')
                    ->iconColor('indigo')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source_name')
                    ->label('Source (legacy)')
                    ->badge()
                    ->color('indigo')
                    ->icon('heroicon-m-globe-alt')
                    ->iconColor('indigo')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('source_domain')
                    ->label('Domain')
                    ->color('violet')
                    ->fontFamily('mono')
                    ->icon('heroicon-m-globe-alt')
                    ->iconColor('violet')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime('M j, H:i')
                    ->icon('heroicon-m-calendar')
                    ->iconColor('sky')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('decoded_url')
                    ->label('Decoded Link')
                    ->icon('heroicon-m-link')
                    ->iconColor('indigo')
                    ->color('indigo')
                    ->copyable()
                    ->url(fn($record) => $record->decoded_url)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn() => 'Open Link'),

                Tables\Columns\TextColumn::make('original_url')
                    ->label('Original Link')
                    ->icon('heroicon-m-link')
                    ->iconColor('slate')
                    ->color('slate')
                    ->copyable()
                    ->url(fn($record) => $record->original_url)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn() => 'Open Original')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('guid')
                    ->label('GUID')
                    ->fontFamily('mono')
                    ->icon('heroicon-m-identification')
                    ->iconColor('rose')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('parentArticles.title')
                    ->label('Clusters')
                    ->badge()
                    ->color('sky')
                    ->icon('heroicon-m-rectangle-group')
                    ->description('Part of cluster')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, H:i')
                    ->icon('heroicon-m-clock')
                    ->iconColor('slate')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
                Tables\Filters\Filter::make('has_decoded_url')
                    ->query(fn($query) => $query->whereNotNull('decoded_url'))
                    ->toggle(),
                Tables\Filters\Filter::make('published_at')
                    ->form([
                        Forms\Components\DatePicker::make('published_from')
                            ->prefixIcon('heroicon-m-calendar')
                            ->placeholder('From date...'),
                        Forms\Components\DatePicker::make('published_until')
                            ->prefixIcon('heroicon-m-calendar')
                            ->placeholder('Until date...'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['published_from'],
                                fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('published_at', '>=', $date),
                            )
                            ->when(
                                $data['published_until'],
                                fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('published_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit Article')
                    ->keyBindings(['command+e', 'ctrl+e']),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete Article')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to delete this article? This action cannot be undone.')
                    ->successNotificationTitle('Article deleted successfully'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\ArticleStatsOverview::class,
        ];
    }

    public static function getRelations(): array
    {
        return [
            ParentArticlesRelationManager::class,
            RelatedArticlesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
