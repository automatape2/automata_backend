<?php

namespace App\Filament\Resources\Visits;

use App\Filament\Resources\Visits\Pages\ManageVisits;
use App\Models\Visit;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class VisitResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Visitas';
    
    protected static ?string $modelLabel = 'Visita';
    
    protected static ?string $pluralModelLabel = 'Visitas';

    protected static ?string $recordTitleAttribute = 'Resource1';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ip_address')
                    ->required(),
                TextInput::make('user_agent'),
                TextInput::make('referer'),
                TextInput::make('url')
                    ->url(),
                TextInput::make('country'),
                TextInput::make('city'),
                TextInput::make('device_type'),
                TextInput::make('browser'),
                TextInput::make('platform'),
                TextInput::make('session_id'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('created_at')
                    ->label('Fecha de Visita')
                    ->dateTime('d/m/Y H:i:s'),
                TextEntry::make('session_id')
                    ->label('ID de Sesión')
                    ->copyable()
                    ->placeholder('-'),
                TextEntry::make('url')
                    ->label('URL Visitada')
                    ->columnSpanFull()
                    ->copyable()
                    ->placeholder('-'),
                TextEntry::make('referer')
                    ->label('Referencia (de dónde vino)')
                    ->columnSpanFull()
                    ->copyable()
                    ->placeholder('Acceso directo'),
                TextEntry::make('ip_address')
                    ->label('Dirección IP')
                    ->copyable(),
                TextEntry::make('country')
                    ->label('País')
                    ->placeholder('Desconocido'),
                TextEntry::make('city')
                    ->label('Ciudad')
                    ->placeholder('Desconocido'),
                TextEntry::make('device_type')
                    ->label('Tipo de Dispositivo')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('browser')
                    ->label('Navegador')
                    ->placeholder('-'),
                TextEntry::make('platform')
                    ->label('Sistema Operativo')
                    ->placeholder('-'),
                TextEntry::make('user_agent')
                    ->label('User Agent Completo')
                    ->columnSpanFull()
                    ->placeholder('-'),
                TextEntry::make('navigation_journey')
                    ->label('🗺️ Recorrido de Navegación (Sesión Completa)')
                    ->columnSpanFull()
                    ->state(function (Visit $record) {
                        if (!$record->session_id) {
                            return 'No hay sesión registrada';
                        }

                        $visits = $record->sessionVisits();
                        
                        if ($visits->isEmpty()) {
                            return 'No hay visitas en esta sesión';
                        }

                        $journey = '';
                        foreach ($visits as $index => $visit) {
                            $isCurrent = $visit->id === $record->id;
                            $marker = $isCurrent ? '➡️' : '•';
                            $time = $visit->created_at->format('H:i:s');
                            $path = parse_url($visit->url, PHP_URL_PATH) ?: $visit->url ?: 'Desconocido';
                            
                            $journey .= sprintf(
                                "%s Paso %d - %s - %s%s\n",
                                $marker,
                                $index + 1,
                                $time,
                                $path,
                                $isCurrent ? ' (Actual)' : ''
                            );
                        }

                        $totalTime = $visits->last()->created_at->diffInMinutes($visits->first()->created_at);
                        $journey .= "\n📊 Total de páginas: {$visits->count()} | ⏱️ Tiempo en sitio: {$totalTime} minutos";

                        return $journey;
                    })
                    ->formatStateUsing(fn (string $state): string => $state)
                    ->extraAttributes(['style' => 'white-space: pre-line; font-family: monospace; background: #f8f9fa; padding: 1rem; border-radius: 0.5rem;'])
                    ->visible(fn (Visit $record) => $record->session_id !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Resource1')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('session_id')
                    ->label('Sesión')
                    ->searchable()
                    ->toggleable()
                    ->copyable()
                    ->formatStateUsing(fn ($state) => $state ? substr($state, 0, 8).'...' : '-')
                    ->description(function (Visit $record): ?string {
                        if (!$record->session_id) return null;
                        $count = Visit::where('session_id', $record->session_id)->count();
                        return $count > 1 ? "{$count} páginas visitadas" : null;
                    }),
                TextColumn::make('url')
                    ->label('Página Visitada')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) > 50) {
                            return $state;
                        }
                        return null;
                    }),
                TextColumn::make('referer')
                    ->label('Desde')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('country')
                    ->label('País')
                    ->searchable()
                    ->toggleable()
                    ->badge()
                    ->color('success'),
                TextColumn::make('city')
                    ->label('Ciudad')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('device_type')
                    ->label('Dispositivo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mobile' => 'info',
                        'desktop' => 'success',
                        'tablet' => 'warning',
                        'bot' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                TextColumn::make('browser')
                    ->label('Navegador')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('platform')
                    ->label('Sistema')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->searchable()
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('device_type')
                    ->label('Tipo de Dispositivo')
                    ->options([
                        'mobile' => 'Mobile',
                        'desktop' => 'Desktop',
                        'tablet' => 'Tablet',
                        'bot' => 'Bot',
                    ]),
                SelectFilter::make('country')
                    ->label('País')
                    ->options(function () {
                        return Visit::whereNotNull('country')
                            ->distinct()
                            ->pluck('country', 'country')
                            ->toArray();
                    }),
                Filter::make('created_at')
                    ->label('Última semana')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subWeek())),
                Filter::make('today')
                    ->label('Hoy')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today())),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVisits::route('/'),
        ];
    }
    
    public static function getWidgets(): array
    {
        return [
            Widgets\VisitsStatsOverview::class,
        ];
    }
}
