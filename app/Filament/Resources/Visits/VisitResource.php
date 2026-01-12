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

                        $journey = "<div style='font-family: monospace; background: #f8f9fa; padding: 1.5rem; border-radius: 0.5rem;'>";
                        
                        foreach ($visits as $index => $visit) {
                            $isCurrent = $visit->id === $record->id;
                            $time = $visit->created_at->format('H:i:s');
                            $path = parse_url($visit->url, PHP_URL_PATH) ?: $visit->url ?: 'Desconocido';
                            
                            // Calcular tiempo entre páginas
                            $timeDiff = '';
                            if ($index > 0) {
                                $prevVisit = $visits[$index - 1];
                                $seconds = $prevVisit->created_at->diffInSeconds($visit->created_at);
                                $timeDiff = "<div style='text-align: center; color: #6b7280; margin: 0.5rem 0;'>⬇️ {$seconds}s</div>";
                            }
                            
                            $bgColor = $isCurrent ? '#4ade80' : '#e5e7eb';
                            $textColor = $isCurrent ? '#000' : '#374151';
                            $border = $isCurrent ? '3px solid #22c55e' : '2px solid #d1d5db';
                            
                            $journey .= $timeDiff;
                            $journey .= "<div style='background: {$bgColor}; color: {$textColor}; border: {$border}; padding: 1rem; border-radius: 0.5rem; margin: 0.5rem 0; text-align: center;'>";
                            $journey .= "<div style='font-weight: bold; font-size: 0.875rem; margin-bottom: 0.25rem;'>Paso " . ($index + 1) . "</div>";
                            $journey .= "<div style='font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem;'>{$time}</div>";
                            $journey .= "<div style='font-size: 1rem;'>{$path}</div>";
                            if ($isCurrent) {
                                $journey .= "<div style='font-size: 0.75rem; margin-top: 0.5rem; font-weight: bold;'>📍 Página Actual</div>";
                            }
                            $journey .= "</div>";
                        }

                        $totalTime = $visits->last()->created_at->diffInMinutes($visits->first()->created_at);
                        $journey .= "<div style='margin-top: 1rem; padding: 1rem; background: #dbeafe; border-radius: 0.5rem; text-align: center; color: #1e40af;'>";
                        $journey .= "<strong>📊 Total de páginas: {$visits->count()}</strong> | ";
                        $journey .= "<strong>⏱️ Tiempo en sitio: {$totalTime} minutos</strong>";
                        $journey .= "</div>";
                        $journey .= "</div>";

                        return $journey;
                    })
                    ->html()
                    ->visible(fn (Visit $record) => $record->session_id !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Resource1')
            ->query(function () {
                // Obtener solo una visita por sesión (la primera)
                return Visit::whereNotNull('session_id')
                    ->whereIn('id', function ($query) {
                        $query->selectRaw('MIN(id)')
                            ->from('visits')
                            ->whereNotNull('session_id')
                            ->groupBy('session_id');
                    })
                    ->orderBy('created_at', 'desc');
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Inicio de Sesión')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('session_pages')
                    ->label('Páginas')
                    ->state(function (Visit $record) {
                        return Visit::where('session_id', $record->session_id)->count();
                    })
                    ->badge()
                    ->color('info'),
                TextColumn::make('session_duration')
                    ->label('Duración')
                    ->state(function (Visit $record) {
                        $visits = Visit::where('session_id', $record->session_id)
                            ->orderBy('created_at')
                            ->get();
                        
                        if ($visits->count() < 2) {
                            return '< 1 min';
                        }
                        
                        $minutes = $visits->first()->created_at->diffInMinutes($visits->last()->created_at);
                        return $minutes > 0 ? "{$minutes} min" : '< 1 min';
                    })
                    ->badge()
                    ->color('warning'),
                TextColumn::make('navigation_path')
                    ->label('Recorrido')
                    ->state(function (Visit $record) {
                        $visits = Visit::where('session_id', $record->session_id)
                            ->orderBy('created_at', 'asc')
                            ->get();
                        
                        $path = $visits->map(function ($visit) {
                            $parsed = parse_url($visit->url, PHP_URL_PATH);
                            return $parsed ?: '/';
                        })->join(' → ');
                        
                        return strlen($path) > 60 ? substr($path, 0, 60).'...' : $path;
                    })
                    ->tooltip(function (Visit $record): ?string {
                        $visits = Visit::where('session_id', $record->session_id)
                            ->orderBy('created_at', 'asc')
                            ->get();
                        
                        return $visits->map(function ($visit) {
                            return parse_url($visit->url, PHP_URL_PATH) ?: '/';
                        })->join(' → ');
                    }),
                TextColumn::make('country')
                    ->label('País')
                    ->badge()
                    ->color('success')
                    ->placeholder('-'),
                TextColumn::make('device_type')
                    ->label('Dispositivo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mobile' => 'info',
                        'desktop' => 'success',
                        'tablet' => 'warning',
                        'bot' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->copyable()
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
