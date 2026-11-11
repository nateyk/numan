<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ReferenceDataController;
use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public reference data endpoints
Route::get('/cargo-types', [ReferenceDataController::class, 'cargoTypes']);
Route::get('/truck-types', [ReferenceDataController::class, 'truckTypes']);
Route::get('/locations', [ReferenceDataController::class, 'locations']);

/*
|--------------------------------------------------------------------------
| Workspace API v1 (authenticated via workspace API keys)
|--------------------------------------------------------------------------
|
| These routes are authenticated using the wsk_ workspace API keys
| generated in the workspace settings. Each route requires the
| api-key middleware with the appropriate scope.
|
*/
use App\Http\Controllers\Api\V1\WorkspaceController;

Route::prefix('v1')->middleware('api-key:read')->group(function () {
    Route::get('/workspace', [WorkspaceController::class, 'show']);
    Route::get('/members', [WorkspaceController::class, 'members']);
});

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('telegram.webhook');
