<?php

namespace App\Filament\Resources\Visits\Pages;

use App\Filament\Resources\Visits\VisitResource;
use App\Filament\Resources\Visits\Widgets\VisitsStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVisits extends ManageRecords
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Removemos el botón de crear ya que las visitas se crean desde la API
        ];
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            VisitsStatsOverview::class,
        ];
    }
}
