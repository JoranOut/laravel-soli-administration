<?php

use App\Http\Controllers\Api\MemberSyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/sync')->middleware(['force.json', 'sync.api', 'throttle:500,30'])->group(function () {
    Route::put('/members/{lid_id}', [MemberSyncController::class, 'upsert'])
        ->where('lid_id', '[0-9]+');

    Route::delete('/members/{lid_id}', [MemberSyncController::class, 'destroy'])
        ->where('lid_id', '[0-9]+');

    Route::post('/reconcile', [MemberSyncController::class, 'reconcile']);
});
