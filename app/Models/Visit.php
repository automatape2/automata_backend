<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    protected $fillable = [
        'ip_address',
        'user_agent',
        'referer',
        'url',
        'country',
        'city',
        'device_type',
        'browser',
        'platform',
        'session_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtiene estadísticas de visitas únicas por IP
     */
    public static function getUniqueVisitorsCount($days = 30)
    {
        return static::where('created_at', '>=', now()->subDays($days))
            ->distinct('ip_address')
            ->count('ip_address');
    }

    /**
     * Obtiene el total de visitas
     */
    public static function getTotalVisitsCount($days = 30)
    {
        return static::where('created_at', '>=', now()->subDays($days))->count();
    }

    /**
     * Obtiene estadísticas por país
     */
    public static function getVisitsByCountry($days = 30)
    {
        return static::where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('country')
            ->groupBy('country')
            ->selectRaw('country, COUNT(*) as count')
            ->orderByDesc('count')
            ->get();
    }

    /**
     * Obtiene todas las visitas de la misma sesión
     */
    public function sessionVisits()
    {
        if (!$this->session_id) {
            return collect([]);
        }

        return static::where('session_id', $this->session_id)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Obtiene el recorrido (journey) de navegación de esta sesión
     */
    public function getNavigationJourney()
    {
        return $this->sessionVisits()
            ->map(function ($visit, $index) {
                return [
                    'step' => $index + 1,
                    'time' => $visit->created_at->format('H:i:s'),
                    'page' => parse_url($visit->url, PHP_URL_PATH) ?: $visit->url,
                    'full_url' => $visit->url,
                ];
            });
    }
}
