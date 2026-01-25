<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    public static function canAccess(): bool
    {
        return true;
    }

    protected static bool $shouldSkipAuthorization = true;

    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'rss_url'];
    }

    public static function getNavigationBadge(): ?string
    {
        return \Illuminate\Support\Facades\Cache::remember('category_count', 300, fn () => (string) static::getModel()::count());
    }

    public static function getModelLabel(): string
    {
        return 'Category';
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return $record?->name;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Category Hierarchy')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Select::make('parent_id')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->searchDebounce(500)
                            ->preload()
                            ->prefixIcon('heroicon-m-folder')
                            ->prefixIconColor('amber')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-folder')
                                    ->prefixIconColor('emerald')
                                    ->placeholder('Enter category name...'),
                            ])
                            ->placeholder('Select Parent Category (Optional)'),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-m-folder')
                            ->prefixIconColor('emerald')
                            ->placeholder('Enter category name...'),
                    ]),                    Forms\Components\Section::make('Configuration')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Textarea::make('rss_url')
                            ->label('RSS Feed URL')
                            ->columnSpanFull()
                            ->placeholder('Enter RSS feed URL...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->poll('10s')
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->weight('bold')
                    ->icon('heroicon-m-folder')
                    ->iconColor('emerald')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->badge()
                    ->color('amber')
                    ->icon('heroicon-m-folder-open')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rss_url')
                    ->icon('heroicon-m-rss')
                    ->iconColor('orange')
                    ->copyable(),
                Tables\Columns\TextColumn::make('articles_count')
                    ->counts('articles')
                    ->badge()
                    ->icon('heroicon-m-document-duplicate')
                    ->color('sky')
                    ->label('Articles')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()),                    ])->filters([
                        //
                    ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit Category'),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete Category')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
