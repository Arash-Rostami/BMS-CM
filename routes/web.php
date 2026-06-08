<?php

use App\Http\Controllers\AttachmentController;
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

Route::get('/test', function () { return view('components.test'); });

Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
    ->name('attachments.download');

Route::get('/api/search/spotlight', [SearchController::class, 'spotlight'])
    ->middleware('auth')
    ->name('search.spotlight');

Route::fallback(fn() => view('errors.404'));
