<?php
use App\Http\Controllers\AdController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\TraefikConfigController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;

// Public Health Check Endpoint
Route::get('/health', [HealthController::class, 'healthCheck']);

// Traefik Dynamic Configuration Endpoint (can be protected or exposed as needed)
// For security, you might want to protect this endpoint with an API key or specific IP range.
Route::get('/traefik/config', [TraefikConfigController::class, 'generateHttpConfig']);

// Authentication Routes
Route::post('/login', [AuthController::class, 'login']);

// Protected API Routes (requires Sanctum token and tenant context)
Route::middleware(['auth:sanctum', 'set.tenant'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Ad Management with RBAC
    # @OA\Parameter(
    #     name="X-Tenant-Id",
    #     in="header",
    #     required=true,
    #     description="Tenant ID for multi-tenancy. Example: For tenant-a.localhost, X-Tenant-Id should be 1.",
    #     @OA\Schema(
    #         type="integer",
    #         format="int64",
    #         example=1
    #     )
    # )
    Route::get('/ads', [AdController::class, 'index'])->middleware('permission:read ads');
    Route::get('/ads/{ad}', [AdController::class, 'show'])->middleware('permission:read ads');
    Route::post('/ads', [AdController::class, 'store'])->middleware('permission:create ads');
    Route::put('/ads/{ad}', [AdController::class, 'update'])->middleware('permission:update ads');
    Route::delete('/ads/{ad}', [AdController::class, 'destroy'])->middleware('permission:delete ads');

    // Report API with permission check
    Route::get('/reports/conversions', [ReportController::class, 'getConversionRate'])->middleware('permission:view reports');
    Route::get('/reports/events', [ReportController::class, 'getEvents'])->middleware('permission:view reports');
});
