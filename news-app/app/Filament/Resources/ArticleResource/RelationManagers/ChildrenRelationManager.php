<?php

namespace App\Filament\Resources\ArticleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Related Articles';

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
                    ->placeholder('Enter the child article title...')
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
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->weight('medium')
                    ->icon('heroicon-m-document-text')
                    ->iconColor('sky')
                    ->tooltip(fn ($record) => $record->title)
                    ->url(fn (\App\Models\Article $record): string => \App\Filament\Resources\ArticleResource::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('source_name')
                    ->badge()
                    ->color('indigo')
                    ->icon('heroicon-m-globe-alt')
                    ->url(fn ($record) => $record->source_url)
                    ->openUrlInNewTab(),

                Tables\Columns\TextInputColumn::make('decoded_url')
                    ->label('Link')
                    ->tooltip('Edit URL inline'),

                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime('M j, H:i')
                    ->icon('heroicon-m-calendar')
                    ->iconColor('sky')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
