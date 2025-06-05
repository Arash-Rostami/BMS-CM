<?php

namespace App\Models\Traits\User;

trait Setting
{
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }
}
