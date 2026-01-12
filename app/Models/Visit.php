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
}
