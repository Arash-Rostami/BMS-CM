<?php

namespace App\Models\Traits\General;


trait Localization
{
    public function newQuery()
    {
        return parent::newQuery()
            ->orderBy($this->localeColumn());
    }

    public function getLocalizedNameAttribute(): string
    {
        return $this->{$this->localeColumn()} ?? '';
    }

    protected function localeColumn(): string
    {
        return app()->getLocale() === 'fa'
            ? 'name'
            : 'english_name';
    }
}
