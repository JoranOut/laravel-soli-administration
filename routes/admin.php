<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BetalingController;
use App\Http\Controllers\Admin\ContributieController;
use App\Http\Controllers\Admin\InstrumentBespelerController;
use App\Http\Controllers\Admin\InstrumentController;
use App\Http\Controllers\Admin\InstrumentReparatieController;
use App\Http\Controllers\Admin\OnderdeelController;
use App\Http\Controllers\Admin\RelatieContactController;
use App\Http\Controllers\Admin\RelatieController;
use App\Http\Controllers\Admin\RelatieDiplomaController;
use App\Http\Controllers\Admin\RelatieInsigneController;
use App\Http\Controllers\Admin\RelatieLidmaatschapController;
use App\Http\Controllers\Admin\RelatieOpleidingController;
use App\Http\Controllers\Admin\RelatieTypeController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\TariefgroepController;
use App\Http\Controllers\Admin\UserRelatieLinkController;
use App\Http\Controllers\Admin\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('admin/roles', [RolePermissionController::class, 'index'])->name('admin.roles.index');
    Route::put('admin/roles/{role}', [RolePermissionController::class, 'update'])->name('admin.roles.update');

    Route::get('admin/users', [UserRoleController::class, 'index'])->name('admin.users.index');
    Route::put('admin/users/{user}', [UserRoleController::class, 'update'])->name('admin.users.update');

    Route::get('admin/koppelingen', [UserRelatieLinkController::class, 'index'])->name('admin.koppelingen.index');
    Route::post('admin/koppelingen', [UserRelatieLinkController::class, 'store'])->name('admin.koppelingen.store');
    Route::delete('admin/koppelingen/{relatie}', [UserRelatieLinkController::class, 'destroy'])->name('admin.koppelingen.destroy');
    Route::delete('admin/koppelingen/users/{user}', [UserRelatieLinkController::class, 'destroyUser'])->name('admin.koppelingen.destroy-user');

    Route::get('admin/activity-log', [ActivityLogController::class, 'index'])->name('admin.activity-log.index');
});

Route::middleware(['auth', 'verified', 'permission:relaties.view'])->group(function () {
    // Relaties
    Route::get('admin/relaties', [RelatieController::class, 'index'])->name('admin.relaties.index');
    Route::get('admin/relaties/create', [RelatieController::class, 'create'])->name('admin.relaties.create')
        ->middleware('permission:relaties.create');
    Route::post('admin/relaties', [RelatieController::class, 'store'])->name('admin.relaties.store')
        ->middleware('permission:relaties.create');
    Route::get('admin/relaties/{relatie}', [RelatieController::class, 'show'])->name('admin.relaties.show');
    Route::put('admin/relaties/{relatie}', [RelatieController::class, 'update'])->name('admin.relaties.update')
        ->middleware('permission:relaties.edit');
    Route::delete('admin/relaties/{relatie}', [RelatieController::class, 'destroy'])->name('admin.relaties.destroy')
        ->middleware('permission:relaties.delete');

    // Relatie account management
    Route::post('admin/relaties/{relatie}/account', [RelatieController::class, 'storeAccount'])->name('admin.relaties.account.store')
        ->middleware('permission:users.edit');
    Route::put('admin/relaties/{relatie}/account', [RelatieController::class, 'updateAccountEmail'])->name('admin.relaties.account.update')
        ->middleware('permission:users.edit');
    Route::put('admin/relaties/{relatie}/account/password', [RelatieController::class, 'resetPassword'])->name('admin.relaties.account.reset-password')
        ->middleware(['permission:users.edit', 'throttle:6,1']);
    Route::delete('admin/relaties/{relatie}/account', [RelatieController::class, 'destroyAccount'])->name('admin.relaties.account.destroy')
        ->middleware('permission:users.edit');

    // Relatie sub-resources
    Route::middleware('permission:relaties.edit')->group(function () {
        Route::post('admin/relaties/{relatie}/adressen', [RelatieContactController::class, 'storeAdres'])->name('admin.relaties.adressen.store');
        Route::put('admin/relaties/{relatie}/adressen/{adres}', [RelatieContactController::class, 'updateAdres'])->name('admin.relaties.adressen.update');
        Route::delete('admin/relaties/{relatie}/adressen/{adres}', [RelatieContactController::class, 'destroyAdres'])->name('admin.relaties.adressen.destroy');

        Route::post('admin/relaties/{relatie}/emails', [RelatieContactController::class, 'storeEmail'])->name('admin.relaties.emails.store');
        Route::put('admin/relaties/{relatie}/emails/{email}', [RelatieContactController::class, 'updateEmail'])->name('admin.relaties.emails.update');
        Route::delete('admin/relaties/{relatie}/emails/{email}', [RelatieContactController::class, 'destroyEmail'])->name('admin.relaties.emails.destroy');

        Route::post('admin/relaties/{relatie}/telefoons', [RelatieContactController::class, 'storeTelefoon'])->name('admin.relaties.telefoons.store');
        Route::put('admin/relaties/{relatie}/telefoons/{telefoon}', [RelatieContactController::class, 'updateTelefoon'])->name('admin.relaties.telefoons.update');
        Route::delete('admin/relaties/{relatie}/telefoons/{telefoon}', [RelatieContactController::class, 'destroyTelefoon'])->name('admin.relaties.telefoons.destroy');

        Route::post('admin/relaties/{relatie}/giro-gegevens', [RelatieContactController::class, 'storeGiroGegeven'])->name('admin.relaties.giro-gegevens.store');
        Route::put('admin/relaties/{relatie}/giro-gegevens/{giroGegeven}', [RelatieContactController::class, 'updateGiroGegeven'])->name('admin.relaties.giro-gegevens.update');
        Route::delete('admin/relaties/{relatie}/giro-gegevens/{giroGegeven}', [RelatieContactController::class, 'destroyGiroGegeven'])->name('admin.relaties.giro-gegevens.destroy');

        Route::post('admin/relaties/{relatie}/types', [RelatieTypeController::class, 'store'])->name('admin.relaties.types.store');
        Route::put('admin/relaties/{relatie}/types/{pivotId}', [RelatieTypeController::class, 'update'])->name('admin.relaties.types.update');
        Route::delete('admin/relaties/{relatie}/types/{pivotId}', [RelatieTypeController::class, 'destroy'])->name('admin.relaties.types.destroy');

        Route::post('admin/relaties/{relatie}/lidmaatschap', [RelatieLidmaatschapController::class, 'storeLidmaatschap'])->name('admin.relaties.lidmaatschap.store');
        Route::put('admin/relaties/{relatie}/lidmaatschap/{relatieSinds}', [RelatieLidmaatschapController::class, 'updateLidmaatschap'])->name('admin.relaties.lidmaatschap.update');
        Route::delete('admin/relaties/{relatie}/lidmaatschap/{relatieSinds}', [RelatieLidmaatschapController::class, 'destroyLidmaatschap'])->name('admin.relaties.lidmaatschap.destroy');

        Route::post('admin/relaties/{relatie}/onderdelen', [RelatieLidmaatschapController::class, 'storeOnderdeel'])->name('admin.relaties.onderdelen.store');
        Route::put('admin/relaties/{relatie}/onderdelen/{pivotId}', [RelatieLidmaatschapController::class, 'updateOnderdeel'])->name('admin.relaties.onderdelen.update');
        Route::delete('admin/relaties/{relatie}/onderdelen/{pivotId}', [RelatieLidmaatschapController::class, 'destroyOnderdeel'])->name('admin.relaties.onderdelen.destroy');

        Route::post('admin/relaties/{relatie}/opleidingen', [RelatieOpleidingController::class, 'store'])->name('admin.relaties.opleidingen.store');
        Route::put('admin/relaties/{relatie}/opleidingen/{opleiding}', [RelatieOpleidingController::class, 'update'])->name('admin.relaties.opleidingen.update');
        Route::delete('admin/relaties/{relatie}/opleidingen/{opleiding}', [RelatieOpleidingController::class, 'destroy'])->name('admin.relaties.opleidingen.destroy');

        Route::post('admin/relaties/{relatie}/insignes', [RelatieInsigneController::class, 'store'])->name('admin.relaties.insignes.store');
        Route::put('admin/relaties/{relatie}/insignes/{insigne}', [RelatieInsigneController::class, 'update'])->name('admin.relaties.insignes.update');
        Route::delete('admin/relaties/{relatie}/insignes/{insigne}', [RelatieInsigneController::class, 'destroy'])->name('admin.relaties.insignes.destroy');

        Route::post('admin/relaties/{relatie}/diplomas', [RelatieDiplomaController::class, 'store'])->name('admin.relaties.diplomas.store');
        Route::put('admin/relaties/{relatie}/diplomas/{diploma}', [RelatieDiplomaController::class, 'update'])->name('admin.relaties.diplomas.update');
        Route::delete('admin/relaties/{relatie}/diplomas/{diploma}', [RelatieDiplomaController::class, 'destroy'])->name('admin.relaties.diplomas.destroy');
    });
});

// Onderdelen
Route::middleware(['auth', 'verified', 'permission:onderdelen.view'])->group(function () {
    Route::get('admin/onderdelen', [OnderdeelController::class, 'index'])->name('admin.onderdelen.index');
    Route::get('admin/onderdelen/{onderdeel}', [OnderdeelController::class, 'show'])->name('admin.onderdelen.show');
    Route::post('admin/onderdelen', [OnderdeelController::class, 'store'])->name('admin.onderdelen.store')
        ->middleware('permission:onderdelen.create');
    Route::put('admin/onderdelen/{onderdeel}', [OnderdeelController::class, 'update'])->name('admin.onderdelen.update')
        ->middleware('permission:onderdelen.edit');
    Route::delete('admin/onderdelen/{onderdeel}', [OnderdeelController::class, 'destroy'])->name('admin.onderdelen.destroy')
        ->middleware('permission:onderdelen.delete');
});

// Instrumenten
Route::middleware(['auth', 'verified', 'permission:instrumenten.view'])->group(function () {
    Route::get('admin/instrumenten', [InstrumentController::class, 'index'])->name('admin.instrumenten.index');
    Route::get('admin/instrumenten/{instrument}', [InstrumentController::class, 'show'])->name('admin.instrumenten.show');
    Route::post('admin/instrumenten', [InstrumentController::class, 'store'])->name('admin.instrumenten.store')
        ->middleware('permission:instrumenten.create');
    Route::put('admin/instrumenten/{instrument}', [InstrumentController::class, 'update'])->name('admin.instrumenten.update')
        ->middleware('permission:instrumenten.edit');
    Route::delete('admin/instrumenten/{instrument}', [InstrumentController::class, 'destroy'])->name('admin.instrumenten.destroy')
        ->middleware('permission:instrumenten.delete');

    Route::middleware('permission:instrumenten.edit')->group(function () {
        Route::post('admin/instrumenten/{instrument}/bespelers', [InstrumentBespelerController::class, 'store'])->name('admin.instrumenten.bespelers.store');
        Route::put('admin/instrumenten/{instrument}/bespelers/{bespeler}', [InstrumentBespelerController::class, 'update'])->name('admin.instrumenten.bespelers.update');
        Route::delete('admin/instrumenten/{instrument}/bespelers/{bespeler}', [InstrumentBespelerController::class, 'destroy'])->name('admin.instrumenten.bespelers.destroy');

        Route::post('admin/instrumenten/{instrument}/reparaties', [InstrumentReparatieController::class, 'store'])->name('admin.instrumenten.reparaties.store');
        Route::put('admin/instrumenten/{instrument}/reparaties/{reparatie}', [InstrumentReparatieController::class, 'update'])->name('admin.instrumenten.reparaties.update');
        Route::delete('admin/instrumenten/{instrument}/reparaties/{reparatie}', [InstrumentReparatieController::class, 'destroy'])->name('admin.instrumenten.reparaties.destroy');
    });
});

// Financieel
Route::middleware(['auth', 'verified', 'permission:financieel.view'])->group(function () {
    Route::get('admin/financieel/tariefgroepen', [TariefgroepController::class, 'index'])->name('admin.financieel.tariefgroepen');
    Route::post('admin/financieel/tariefgroepen', [TariefgroepController::class, 'store'])->name('admin.financieel.tariefgroepen.store')
        ->middleware('permission:financieel.create');
    Route::put('admin/financieel/tariefgroepen/{tariefgroep}', [TariefgroepController::class, 'update'])->name('admin.financieel.tariefgroepen.update')
        ->middleware('permission:financieel.edit');
    Route::delete('admin/financieel/tariefgroepen/{tariefgroep}', [TariefgroepController::class, 'destroy'])->name('admin.financieel.tariefgroepen.destroy')
        ->middleware('permission:financieel.delete');

    Route::get('admin/financieel/contributies', [ContributieController::class, 'index'])->name('admin.financieel.contributies');
    Route::post('admin/financieel/contributies', [ContributieController::class, 'store'])->name('admin.financieel.contributies.store')
        ->middleware('permission:financieel.create');
    Route::delete('admin/financieel/contributies/{contributie}', [ContributieController::class, 'destroy'])->name('admin.financieel.contributies.destroy')
        ->middleware('permission:financieel.delete');

    Route::get('admin/financieel/betalingen', [BetalingController::class, 'index'])->name('admin.financieel.betalingen');
    Route::post('admin/financieel/betalingen/{teBetakenContributie}', [BetalingController::class, 'store'])->name('admin.financieel.betalingen.store')
        ->middleware('permission:financieel.edit');
});
