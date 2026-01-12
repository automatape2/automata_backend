<?php

namespace App\Filament\Resources\Visits\Widgets;

use App\Models\Visit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VisitsStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total de Visitas', Visit::count())
                ->description('Visitas totales registradas')
                ->descriptionIcon('heroicon-o-eye')
                ->color('success'),
                
            Stat::make('Hoy', Visit::whereDate('created_at', today())->count())
                ->description('Visitas de hoy')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('info'),
                
            Stat::make('Esta Semana', Visit::where('created_at', '>=', now()->subWeek())->count())
                ->description('Últimos 7 días')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('warning'),
                
            Stat::make('Visitantes Únicos', Visit::distinct('ip_address')->count('ip_address'))
                ->description('IPs únicas (total)')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('primary'),
                
            Stat::make('Países', Visit::whereNotNull('country')->distinct('country')->count('country'))
                ->description('Países diferentes')
                ->descriptionIcon('heroicon-o-globe-alt')
                ->color('success'),
                
            Stat::make('Página Más Vista', function () {
                $topPage = Visit::whereNotNull('url')
                    ->groupBy('url')
                    ->selectRaw('url, COUNT(*) as count')
                    ->orderByDesc('count')
                    ->first();
                    
                if ($topPage) {
                    $path = parse_url($topPage->url, PHP_URL_PATH) ?: '/';
                    return $path . ' (' . $topPage->count . ')';
                }
                return 'N/A';
            })
                ->description('Ruta más popular')
                ->descriptionIcon('heroicon-o-document-text')
                ->color('info'),
        ];
    }
}
