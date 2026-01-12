<?php

use App\Http\Controllers\Api\VisitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Endpoint para registrar visitas
    Route::post('/visits', [VisitController::class, 'store']);
    
    // Endpoint para obtener estadísticas (opcional, puede requerir autenticación)
    Route::get('/visits/stats', [VisitController::class, 'stats']);
});
