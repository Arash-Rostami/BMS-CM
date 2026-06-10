<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;


Route::get('/', fn() => redirect()->to('dashboard'));

Route::get('/clear', function () {
    if (!Auth::check()) abort(403, 'Unauthorized');

    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');
    Artisan::call('filament:clear-cached-components');

    return response()->json([
        'message' => 'All caches, including Filament caches, have been cleared successfully!',
        'timestamp' => now()->toDateTimeString()
    ]);
});

Route::get('/cache', function () {
    if (!Auth::check()) abort(403, 'Unauthorized');

    // Rebuild caches
    Artisan::call('config:cache');
    Artisan::call('route:cache');
    Artisan::call('view:cache');
    Artisan::call('filament:cache-components');

    return response()->json([
        'message' => 'All caches, including Filament caches, have been rebuilt successfully!',
        'timestamp' => now()->toDateTimeString()
    ]);
});


// ── Google OAuth ──────────────────────────────────────────────────────────

Route::get('/auth/{provider}/redirect', function (string $provider) {
    abort_unless($provider === 'google', 404);
//    return \Laravel\Socialite\Facades\Socialite::driver($provider)->redirect();
})->name('socialite.redirect');

Route::get('/auth/{provider}/callback', function (string $provider) {
    abort_unless($provider === 'google', 404);

//    $social = \Laravel\Socialite\Facades\Socialite::driver($provider)->user();

//    $user = \App\Models\User::firstOrCreate(
//        ['email' => $social->getEmail()],
//        ['name' => $social->getName(), 'password' => \Illuminate\Support\Str::random(32)]
//    );
//
//    \Illuminate\Support\Facades\Auth::login($user, true);

    return redirect()->intended(filament()->getHomeUrl());
})->name('socialite.callback');


Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
    ->name('attachments.download');

Route::get('/api/search/spotlight', [SearchController::class, 'spotlight'])
    ->middleware('auth')
    ->name('search.spotlight');

Route::get('/shipments/{shipment}/invoice/pdf', [InvoiceController::class, 'shipmentPdf'])
    ->middleware('auth')
    ->name('shipments.invoice.pdf');

Route::fallback(fn() => view('errors.404'));
