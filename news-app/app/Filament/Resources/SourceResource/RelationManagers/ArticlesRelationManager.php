<?php

namespace App\Filament\Resources\SourceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->prefixIcon('heroicon-m-document-text')
                    ->prefixIconColor('sky')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('decoded_url')
                    ->url()
                    ->prefixIcon('heroicon-m-link')
                    ->prefixIconColor('indigo'),
                Forms\Components\DateTimePicker::make('published_at')
                    ->prefixIcon('heroicon-m-calendar')
                    ->prefixIconColor('sky'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->weight('medium')
                    ->icon('heroicon-m-document-text')
                    ->iconColor('sky')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->title)
                    ->url(fn ($record): string => \App\Filament\Resources\ArticleResource::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime('M j, H:i')
                    ->icon('heroicon-m-calendar')
                    ->iconColor('sky')
                    ->sortable(),

                Tables\Columns\TextColumn::make('decoded_url')
                    ->label('Link')
                    ->icon('heroicon-m-link')
                    ->iconColor('indigo')
                    ->color('primary')
                    ->copyable()
                    ->url(fn ($record) => $record->decoded_url)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn (): string => 'Open'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->color('info')
                    ->url(fn ($record): string => \App\Filament\Resources\ArticleResource::getUrl('view', ['record' => $record])),
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->color('warning')
                    ->url(fn ($record): string => \App\Filament\Resources\ArticleResource::getUrl('edit', ['record' => $record])),
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
}
