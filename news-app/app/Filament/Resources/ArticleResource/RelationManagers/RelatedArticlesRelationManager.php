<?php

namespace App\Filament\Resources\ArticleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RelatedArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'relatedArticles';

    protected static ?string $title = 'Related News (Children)';

    protected static bool $isLazy = false;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->prefixIcon('heroicon-m-document-text')
                    ->prefixIconColor('sky')
                    ->placeholder('Enter the related article title...')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('decoded_url')
                    ->url()
                    ->prefixIcon('heroicon-m-link')
                    ->prefixIconColor('indigo')
                    ->placeholder('Enter decoded URL...'),
                Forms\Components\TextInput::make('source_name')
                    ->prefixIcon('heroicon-m-globe-alt')
                    ->prefixIconColor('indigo')
                    ->placeholder('Enter source name...'),
                Forms\Components\TextInput::make('source_url')
                    ->url()
                    ->prefixIcon('heroicon-m-link')
                    ->prefixIconColor('slate')
                    ->placeholder('Enter source URL...'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->recordTitleAttribute('title')
            ->defaultSort('published_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-m-document-text')
                    ->iconColor('sky')
                    ->tooltip(fn($record) => $record->title)
                    ->url(fn(\App\Models\Article $record): string => \App\Filament\Resources\ArticleResource::getUrl('edit', ['record' => $record]))
                    ->wrap(),

                Tables\Columns\TextColumn::make('categories.name')
                    ->badge()
                    ->color('emerald')
                    ->icon('heroicon-m-tag')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('source_name')
                    ->label('Source')
                    ->badge()
                    ->color('indigo')
                    ->icon('heroicon-m-globe-alt')
                    ->url(fn($record) => $record->source_url)
                    ->openUrlInNewTab()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('decoded_url')
                    ->label('Link')
                    ->icon('heroicon-m-link')
                    ->iconColor('indigo')
                    ->color('indigo')
                    ->copyable()
                    ->url(fn($record) => $record->decoded_url)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn() => 'Open')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('M j, H:i')
                    ->icon('heroicon-m-calendar')
                    ->iconColor('sky')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M j, H:i')
                    ->icon('heroicon-m-clock')
                    ->iconColor('slate')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add New Related')
                    ->icon('heroicon-m-plus-circle'),
                Tables\Actions\AssociateAction::make()
                    ->label('Link Existing')
                    ->icon('heroicon-m-link')
                    ->preloadRecordSelect()
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit'),
                Tables\Actions\DissociateAction::make()
                    ->iconButton()
                    ->tooltip('Unlink')
                    ->color('warning')
                    ->requiresConfirmation(),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DissociateBulkAction::make()
                        ->requiresConfirmation(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading('No related news (children)')
            ->emptyStateDescription('Add or link children articles to group similar news together.')
            ->emptyStateIcon('heroicon-o-newspaper');
    }
}
