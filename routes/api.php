<?php

use App\Http\Controllers\Api\MemberSyncController;
use App\Http\Controllers\Api\OidcUserinfoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('/oauth/userinfo', OidcUserinfoController::class);
});

Route::prefix('v1/sync')->middleware(['force.json', 'client', 'throttle:500,30'])->group(function () {
    Route::put('/members/{lid_id}', [MemberSyncController::class, 'upsert'])
        ->where('lid_id', '[0-9]+');

    Route::delete('/members/{lid_id}', [MemberSyncController::class, 'destroy'])
        ->where('lid_id', '[0-9]+');

    Route::post('/reconcile', [MemberSyncController::class, 'reconcile']);
});
