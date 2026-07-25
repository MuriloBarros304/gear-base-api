<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PartController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\MaintenanceController;

// Cria automaticamente rotas GET, POST, PUT, DELETE para cada recurso
Route::apiResource('parts', PartController::class);
Route::apiResource('tools', ToolController::class);
Route::apiResource('maintenances', MaintenanceController::class);
