<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SOSMessageController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/api/sos', [SOSMessageController::class, 'store']);

// Synchronization endpoints (exposed under /api/* for clients)
use App\Http\Controllers\SyncController;

// Register sync endpoints under the `api` middleware group to avoid CSRF checks
Route::middleware('api')->group(function () {
    Route::post('/api/sync/upload', [SyncController::class, 'upload']);
    Route::get('/api/sync/download', [SyncController::class, 'download']);
});
// Register sync endpoints under the `api` middleware group and explicitly
// remove CSRF middleware to allow stateless clients (mobile devices) to POST.
Route::middleware('api')
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ])
    ->group(function () {
        Route::post('/api/sync/upload', [SyncController::class, 'upload']);
        Route::get('/api/sync/download', [SyncController::class, 'download']);
});
