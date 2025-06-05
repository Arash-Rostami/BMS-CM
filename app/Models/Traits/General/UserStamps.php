<?php

namespace App\Models\Traits\General;

use Illuminate\Database\Eloquent\Model;

trait UserStamps
{
    protected static function bootUserStamps()
    {
        static::creating(function (Model $model) {
            if (auth()->check()) {
                $model->user_id = auth()->id();
            }
        });

        static::updating(function (Model $model) {
            if ($model->isDirty() && auth()->check()) {
                $model->updated_by_id = auth()->id();
            }
        });
    }
}
