<?php

namespace App\Models\Traits\General;

use App\Models\Product;

trait HasProductCategoryFormatting
{
    public function getTargetableFormatted(string $format = 'table'): string
    {
        if (! $this->targetable) {
            return '-';
        }

        $name = $this->targetable_type === Product::class
            ? ($this->targetable->customized_label ?? $this->targetable->getLocalizedNameAttribute())
            : $this->targetable->getLocalizedNameAttribute();

        $type = match ($format) {
            'table' => $this->targetable_type === Product::class ? '🗑️' : '🗂️',
            'export' => match ($this->targetable_type) {
                Product::class => app()->isLocale('fa') ? '(محصول)' : '(Product)',
                default => app()->isLocale('fa') ? '(دسته)' : '(Category)'
            }
        };

        return "{$name} {$type}";
    }
}
