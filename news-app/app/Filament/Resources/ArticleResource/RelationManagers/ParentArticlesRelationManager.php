<?php

namespace App\Filament\Resources\ArticleResource\RelationManagers;

use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ParentArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'parentArticles';

    protected static ?string $title = 'Parent News (Clusters)';

    protected static bool $isLazy = false;

    public function form(Form $form): Form
    {
        return ArticleResource::form($form);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-m-rectangle-stack')
                    ->iconColor('amber')
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record]))
                    ->wrap(),

                Tables\Columns\TextColumn::make('source_name')
                    ->label('Source')
                    ->badge()
                    ->color('indigo'),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('M j, H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AssociateAction::make()
                    ->label('Link to Parent')
                    ->icon('heroicon-m-link')
                    ->preloadRecordSelect()
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\DissociateAction::make()
                    ->iconButton()
                    ->tooltip('Unlink from Parent')
                    ->color('warning')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DissociateBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading('No parent news (clusters)')
            ->emptyStateDescription('This article is currently a standalone main article.')
            ->emptyStateIcon('heroicon-o-folder');
    }
}
