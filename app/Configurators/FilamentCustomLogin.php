<?php

namespace App\Configurators;

use App\Http\Responses\FilamentLoginResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Contracts\Foundation\Application;

class FilamentCustomLogin
{
    public static function configure(Application $app): void
    {
        $app->bind(LoginResponseContract::class, FilamentLoginResponse::class);
    }
}
