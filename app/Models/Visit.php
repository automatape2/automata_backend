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

    /**
     * Genera un diagrama Mermaid del recorrido de navegación
     */
    public function getMermaidJourney()
    {
        if (!$this->session_id) {
            return null;
        }

        $visits = $this->sessionVisits();
        
        if ($visits->isEmpty()) {
            return null;
        }

        $mermaid = "graph LR\n";
        
        foreach ($visits as $index => $visit) {
            $nodeId = "step" . ($index + 1);
            $time = $visit->created_at->format('H:i:s');
            $path = parse_url($visit->url, PHP_URL_PATH) ?: '/';
            $path = str_replace(['[', ']', '(', ')'], '', $path); // Sanitizar para Mermaid
            
            $isCurrent = $visit->id === $this->id;
            $style = $isCurrent ? ":::current" : "";
            
            $mermaid .= "    {$nodeId}[\"{$time}<br/>{$path}\"]{$style}\n";
            
            if ($index < $visits->count() - 1) {
                $nextNodeId = "step" . ($index + 2);
                $diffSeconds = $visit->created_at->diffInSeconds($visits[$index + 1]->created_at);
                $mermaid .= "    {$nodeId} -->|{$diffSeconds}s| {$nextNodeId}\n";
            }
        }
        
        $mermaid .= "\n    classDef current fill:#4ade80,stroke:#22c55e,stroke-width:3px,color:#000\n";
        
        return $mermaid;
    }
}
