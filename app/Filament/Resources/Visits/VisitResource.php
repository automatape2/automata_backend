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

                        $totalPages = $visits->count();
                        $totalTime = $visits->last()->created_at->diffInMinutes($visits->first()->created_at);
                        
                        $uniqueId = uniqid();
                        
                        // Crear HTML de nodos
                        $nodesHtml = '';
                        foreach ($visits as $index => $visit) {
                            $path = parse_url($visit->url, PHP_URL_PATH) ?: '/';
                            $time = $visit->created_at->format('H:i:s');
                            $isCurrent = $visit->id === $record->id;
                            $bgColor = $isCurrent ? '#4ade80' : '#3b82f6';
                            $borderColor = $isCurrent ? '#22c55e' : '#2563eb';
                            $borderWidth = $isCurrent ? '3px' : '2px';
                            
                            $nodesHtml .= "<div style='display: flex; align-items: center; gap: 1rem;'>";
                            
                            // Nodo
                            $nodesHtml .= "<div style='min-width: 180px; background: {$bgColor}; color: white; padding: 1.5rem; border-radius: 0.75rem; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: {$borderWidth} solid {$borderColor};'>";
                            $nodesHtml .= "<div style='font-weight: bold; font-size: 1rem; margin-bottom: 0.5rem;'>Paso " . ($index + 1) . "</div>";
                            $nodesHtml .= "<div style='font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.75rem;'>🕐 {$time}</div>";
                            $nodesHtml .= "<div style='font-size: 1.125rem; font-weight: bold; word-break: break-word;'>{$path}</div>";
                            if ($isCurrent) {
                                $nodesHtml .= "<div style='font-size: 0.875rem; margin-top: 0.75rem; background: rgba(255,255,255,0.2); padding: 0.25rem; border-radius: 0.25rem;'>📍 Esta visita</div>";
                            }
                            $nodesHtml .= "</div>";
                            
                            // Flecha si no es el último
                            if ($index < $visits->count() - 1) {
                                $duration = $visit->created_at->diffInSeconds($visits[$index + 1]->created_at);
                                $nodesHtml .= "<div style='display: flex; flex-direction: column; align-items: center; padding: 0 1rem;'>";
                                $nodesHtml .= "<div style='color: #6b7280; font-size: 0.875rem; font-weight: bold; margin-bottom: 0.25rem;'>{$duration}s</div>";
                                $nodesHtml .= "<div style='color: #3b82f6; font-size: 2.5rem; font-weight: bold; line-height: 1;'>→</div>";
                                $nodesHtml .= "</div>";
                            }
                            
                            $nodesHtml .= "</div>";
                        }

                        return <<<HTML
<div style="background: #f8f9fa; padding: 2rem; border-radius: 0.5rem;">
    <div style="margin-bottom: 1.5rem; padding: 1rem; background: #dbeafe; border-radius: 0.5rem; text-align: center; color: #1e40af;">
        <strong style="font-size: 1.125rem;">📊 {$totalPages} páginas visitadas</strong>
        <span style="margin: 0 1rem;">|</span>
        <strong style="font-size: 1.125rem;">⏱️ {$totalTime} minutos en total</strong>
    </div>
    
    <div style="background: white; padding: 2rem; border-radius: 0.5rem; overflow-x: auto;">
        <div style="display: flex; align-items: center; gap: 0;">
            {$nodesHtml}
        </div>
    </div>
</div>
HTML;
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
