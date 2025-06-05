<?php

use Illuminate\Support\Facades\Route;


Route::get('/', fn () => redirect()->to('dashboard'));


Route::get('/clear', function () {
    if (!Auth::check()) {
        abort(403, 'Unauthorized');
    }

    Artisan::call('schedule:clear-cache');
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');

    Artisan::call('storage:link');

    return "All caches have been cleared and storage symlink created successfully!";
});

Route::fallback(fn() => view('errors.404'));
