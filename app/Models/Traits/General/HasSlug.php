<?php

namespace App\Models\Traits\General;

use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug()
    {
        static::saving(function ($model) {
            if (!empty($model->english_name) && ($model->isDirty('english_name') || !$model->exists)) {
                $slug = Str::slug($model->english_name);
                $originalSlug = $slug;
                $count = 1;

                while (static::where('slug', $slug)->where('id', '!=', $model->id)->exists()) {
                    $slug = $originalSlug . '-' . $count++;
                }

                $model->slug = $slug;
            }
        });
    }
}
