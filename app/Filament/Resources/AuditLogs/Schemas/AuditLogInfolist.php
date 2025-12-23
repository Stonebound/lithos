<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Audit Log Details')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Timestamp')
                            ->dateTime(),
                        TextEntry::make('user.name')
                            ->label('User')
                            ->placeholder('System'),
                        TextEntry::make('action')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'create' => 'success',
                                'update' => 'warning',
                                'delete' => 'danger',
                                'deploy' => 'info',
                                'prepare' => 'primary',
                                'create_zip' => 'secondary',
                                default => 'secondary',
                            }),
                        TextEntry::make('model_type')
                            ->label('Entity Type')
                            ->formatStateUsing(fn (string $state): string => class_basename($state)),
                        TextEntry::make('model_id')
                            ->label('Entity ID'),
                        TextEntry::make('ip_address')
                            ->label('IP Address')
                            ->placeholder('-'),
                        TextEntry::make('user_agent')
                            ->label('User Agent')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Changes')
                    ->schema([
                        TextEntry::make('old_values')
                            ->label('Previous Values')
                            ->placeholder('No previous values')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : null)
                            ->columnSpanFull()
                            ->copyable(),
                        TextEntry::make('new_values')
                            ->label('New Values')
                            ->placeholder('No new values')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : null)
                            ->columnSpanFull()
                            ->copyable(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
