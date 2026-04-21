<?php

use App\Http\Controllers\Api\InstrumentSyncController;
use App\Http\Controllers\Api\MemberSyncController;
use App\Http\Controllers\Api\OidcUserinfoController;
use App\Http\Controllers\Api\OnderdeelSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('/oauth/userinfo', OidcUserinfoController::class);
});

Route::prefix('v1/sync')->middleware(['force.json', 'sync.api_key', 'throttle:500,30'])->group(function () {
    Route::put('/members/{lid_id}', [MemberSyncController::class, 'upsert'])
        ->where('lid_id', '[0-9]+');

    Route::delete('/members/{lid_id}', [MemberSyncController::class, 'destroy'])
        ->where('lid_id', '[0-9]+');

    Route::post('/reconcile', [MemberSyncController::class, 'reconcile']);
});

Route::prefix('v1')->middleware(['force.json', 'instruments.api_key', 'throttle:500,30'])->group(function () {
    Route::get('/instruments', [InstrumentSyncController::class, 'index']);
    Route::get('/onderdelen', [OnderdeelSyncController::class, 'index']);
});
