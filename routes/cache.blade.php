<?php

use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {

    Route::get('/clear', function () {
        abort_unless(Auth::user()->hasRole(UserRole::ADMIN_JUNIOR->value), 403);

        clearApplicationCaches();

        return response()->json([
            'message' => 'Caches cleared successfully!', 'timestamp' => now()->toDateTimeString(),
        ]);
    });

    Route::get('/cache', function () {
        abort_unless(Auth::user()->hasRole(UserRole::ADMIN_JUNIOR->value), 403);

        cacheApplicationConfig();

        return response()->json([
            'message' => 'Caches set successfully!', 'timestamp' => now()->toDateTimeString(),
        ]);
    });

    Route::get('/reset', function () {
        abort_unless(Auth::user()->hasRole([UserRole::ADMIN_JUNIOR->value, UserRole::ADMIN_SENIOR->value]), 403);

        resetApplicationCache();

        return response()->json([
            'message' => 'DB memory refreshed!', 'timestamp' => now()->toDateTimeString(),
        ]);
    });

});
