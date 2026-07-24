<?php

namespace App\Models\Traits\Correspondence;

use Illuminate\Database\Eloquent\Builder;

trait HasThreadGroup
{
    public function getThreadKey(): string
    {
        return (string) ($this->parent_id ?? $this->id);
    }

    public function getThreadTitle(): string
    {
        $threadId = $this->parent_id ?? $this->id;
        $subject = $this->parent->subject ?? $this->subject;

        $senders = self::query()
            ->where('id', $threadId)
            ->orWhere('parent_id', $threadId)
            ->with('creator')
            ->get()
            ->pluck('creator.name')
            ->unique()
            ->join(' ⋮ ');

        return $subject.' ('.$senders.')';
    }

    public static function orderThreadQuery(Builder $query, string $direction): Builder
    {
        return $query->orderByRaw("COALESCE(parent_id, id) $direction, id desc");
    }

    public static function scopeThreadQuery(Builder $query, string $key): Builder
    {
        return $query->where(fn ($q) => $q->where('id', $key)->orWhere('parent_id', $key));
    }
}
