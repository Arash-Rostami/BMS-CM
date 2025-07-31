<?php
namespace App\Models\Traits\Status;
use Illuminate\Database\Eloquent\Builder;

trait HasSearchableRelations
{
    private static array $statusWords = [
        'positive' => ['yes', 'authorized', 'approved', 'ok', 'allow', 'accept', 'grant', 'confirm', 'confirmed', 'permit', 'validate', 'pass', 'go', 'positive', 'good', 'true', 'enable', 'enabled', 'active', 'success', 'successful', 'done', 'complete', 'valid', 'cleared', 'proceed', 'welcome', 'approve', 'verify', 'authenticate', 'unlock', 'open', 'activate', 'بله', 'تایید', 'موافق', 'قبول', 'مجاز', 'تصویب', 'پذیرش', 'صحیح', 'درست', 'حتما', 'آره', 'باشه', 'تایید شده', 'فعال', 'باز', 'روشن'],
        'negative' => ['no', 'denied', 'declined', 'rejected', 'refuse', 'forbid', 'prohibit', 'cancel', 'block', 'veto', 'disallow', 'stop', 'negative', 'bad', 'false', 'disable', 'disabled', 'inactive', 'failure', 'failed', 'abort', 'incomplete', 'invalid', 'blocked', 'halt', 'reject', 'deny', 'revoke', 'lock', 'close', 'deactivate', 'نه', 'رد', 'مخالف', 'لغو', 'ممنوع', 'باطل', 'نپذیرفتن', 'غلط', 'اشتباه', 'هرگز', 'نخیر', 'غیرفعال', 'بسته', 'خاموش']
    ];

    public function scopeSearchStatus(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if (!$term) return $query;

        $lower = mb_strtolower($term, 'UTF-8');

        return $query->where(function (Builder $q) use ($lower, $term) {
            $q->whereRaw('LOWER(name) LIKE LOWER(?)', ["%{$term}%"])
                ->orWhereRaw('LOWER(english_name) LIKE LOWER(?)', ["%{$term}%"]);

            if ($this->matchesWords($lower, 'positive')) $q->orWhere('name', 'Authorized');
            if ($this->matchesWords($lower, 'negative')) $q->orWhere('name', 'Declined');
        });
    }

    private function matchesWords(string $term, string $type): bool
    {
        $words = self::$statusWords[$type];

        return in_array($term, $words, true) ||
            collect($words)->contains(fn($word) => str_contains($word, $term) || str_contains($term, $word));
    }
}
