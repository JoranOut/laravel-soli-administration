<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('contact', [ContactController::class, 'index'])->name('contact');
});

Route::post('locale/{locale}', function (string $locale) {
    if (in_array($locale, ['nl', 'en'])) {
        session()->put('locale', $locale);

        if ($user = auth()->user()) {
            $user->update(['locale' => $locale]);
        }
    }

    return back();
})->middleware('auth')->name('locale.switch');

Route::get('/oauth/logout', function (\Illuminate\Http\Request $request) {
    $redirectUri = $request->query('redirect_uri');

    auth()->guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    if ($redirectUri && filter_var($redirectUri, FILTER_VALIDATE_URL)) {
        return redirect($redirectUri);
    }

    return redirect('/');
})->name('oauth.logout');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
