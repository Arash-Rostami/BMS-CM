<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HasContent implements ValidationRule
{
    public function __construct(protected string $error)
    {

    }

    /**
     * Run the validation rule.
     *
     * @param \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->hasContent($value)) $fail($this->error);
    }

    private function hasContent(mixed $node): bool
    {
        if (is_array($node)) {
            if (isset($node['text']) && !blank(trim($node['text']))) return true;

            if (isset($node['content']) && is_array($node['content'])) {
                foreach ($node['content'] as $child) {
                    if ($this->hasContent($child)) {
                        return true;
                    }
                }
            }
            return false;
        }

        return !blank(trim(strip_tags((string)$node, '<img><iframe>')));
    }
}
