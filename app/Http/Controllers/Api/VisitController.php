<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

class VisitController extends Controller
{
    /**
     * Registra una visita a la web
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'url' => 'nullable|string|max:500',
            'session_id' => 'nullable|string|max:255',
        ]);

        $agent = new Agent();
        $agent->setUserAgent($request->userAgent());

        $visit = Visit::create([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'url' => $validated['url'] ?? $request->header('referer'),
            'session_id' => $validated['session_id'] ?? null,
            'device_type' => $this->getDeviceType($agent),
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
            // country y city se pueden agregar con un servicio de geolocalización
            'country' => null,
            'city' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visita registrada correctamente',
            'visit_id' => $visit->id,
        ], 201);
    }

    /**
     * Obtiene estadísticas de visitas
     */
    public function stats(Request $request)
    {
        $days = $request->input('days', 30);

        return response()->json([
            'total_visits' => Visit::getTotalVisitsCount($days),
            'unique_visitors' => Visit::getUniqueVisitorsCount($days),
            'visits_by_country' => Visit::getVisitsByCountry($days),
            'period_days' => $days,
        ]);
    }

    /**
     * Determina el tipo de dispositivo
     */
    private function getDeviceType(Agent $agent): string
    {
        if ($agent->isRobot()) {
            return 'bot';
        }
        
        if ($agent->isMobile()) {
            return 'mobile';
        }
        
        if ($agent->isTablet()) {
            return 'tablet';
        }
        
        if ($agent->isDesktop()) {
            return 'desktop';
        }

        return 'unknown';
    }
}
