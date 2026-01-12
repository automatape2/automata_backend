<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;
use Stevebauman\Location\Facades\Location;

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
        
        // Obtener geolocalización desde la IP
        $ipAddress = $request->ip();
        $location = null;
        
        // No intentar geolocalizar IPs locales
        if (!in_array($ipAddress, ['127.0.0.1', 'localhost', '::1'])) {
            try {
                $location = Location::get($ipAddress);
            } catch (\Exception $e) {
                // Si falla la geolocalización, continuar sin ella
                $location = null;
            }
        }

        $visit = Visit::create([
            'ip_address' => $ipAddress,
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'url' => $validated['url'] ?? $request->header('referer'),
            'session_id' => $validated['session_id'] ?? null,
            'device_type' => $this->getDeviceType($agent),
            'browser' => $agent->browser() . ' ' . $agent->version($agent->browser()),
            'platform' => $agent->platform() . ' ' . $agent->version($agent->platform()),
            'country' => $location?->countryName,
            'city' => $location?->cityName,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visita registrada correctamente',
            'visit_id' => $visit->id,
            'debug' => [
                'ip' => $ipAddress,
                'browser' => $agent->browser() . ' ' . $agent->version($agent->browser()),
                'platform' => $agent->platform() . ' ' . $agent->version($agent->platform()),
                'device' => $this->getDeviceType($agent),
                'country' => $location?->countryName,
                'city' => $location?->cityName,
                'region' => $location?->regionName,
                'postal_code' => $location?->postalCode,
                'latitude' => $location?->latitude,
                'longitude' => $location?->longitude,
                'timezone' => $location?->timezone,
                'is_robot' => $agent->isRobot(),
                'robot_name' => $agent->robot(),
            ]
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
