<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SourceResource\Pages;
use App\Filament\Resources\SourceResource\RelationManagers;
use App\Models\Source;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SourceResource extends Resource
{
    public static function canAccess(): bool
    {
        return true;
    }

    protected static bool $shouldSkipAuthorization = true;

    protected static ?string $model = Source::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->prefixIcon('heroicon-m-globe-alt')
                            ->prefixIconColor('indigo'),
                        Forms\Components\TextInput::make('domain')
                            ->maxLength(255)
                            ->prefixIcon('heroicon-m-globe-alt')
                            ->prefixIconColor('violet')
                            ->placeholder('e.g. nytimes.com'),
                        Forms\Components\TextInput::make('url')
                            ->url()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-m-link')
                            ->prefixIconColor('slate'),
                        Forms\Components\Toggle::make('is_active')
                            ->required()
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Source Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->weight('bold')
                            ->icon('heroicon-m-globe-alt')
                            ->iconColor('indigo'),
                        Infolists\Components\TextEntry::make('domain')
                            ->icon('heroicon-m-globe-alt')
                            ->iconColor('violet')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('url')
                            ->label('Website')
                            ->url(fn ($record) => $record->url)
                            ->openUrlInNewTab()
                            ->icon('heroicon-m-link')
                            ->iconColor('slate')
                            ->color('primary'),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Status')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime()
                            ->icon('heroicon-m-clock')
                            ->iconColor('slate'),
                        Infolists\Components\TextEntry::make('articles_count')
                            ->label('Total Articles')
                            ->state(fn ($record) => $record->articles()->count())
                            ->badge()
                            ->color('amber'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('articles'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-globe-alt')
                    ->iconColor('indigo'),
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->color('gray')
                    ->icon('heroicon-m-globe-alt')
                    ->iconColor('violet')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Total Articles')
                    ->badge()
                    ->color('amber')
                    ->sortable(),
                Tables\Columns\TextColumn::make('url')
                    ->searchable()
                    ->copyable()
                    ->limit(50)
                    ->url(fn ($record) => $record->url)
                    ->openUrlInNewTab()
                    ->icon('heroicon-m-link')
                    ->iconColor('slate')
                    ->color('primary'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->icon('heroicon-m-clock')
                    ->iconColor('slate')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->color('info'),
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->color('warning'),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->color('danger'),
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
            RelationManagers\ArticlesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSources::route('/'),
            'create' => Pages\CreateSource::route('/create'),
            'view' => Pages\ViewSource::route('/{record}'),
            'edit' => Pages\EditSource::route('/{record}/edit'),
        ];
    }
}
