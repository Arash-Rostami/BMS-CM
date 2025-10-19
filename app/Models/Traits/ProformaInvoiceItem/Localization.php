<?php

namespace App\Models\Traits\ProformaInvoiceItem;

trait Localization
{
    public function newQuery()
    {
        return parent::newQuery();
    }

    public function getLocalizedNameAttribute(): string
    {
        return $this->{$this->localeColumn()} ?? '';
    }

    protected function localeColumn(): string
    {
        return app()->getLocale() === 'fa'
            ? 'description'
            : 'english_description';
    }
}
