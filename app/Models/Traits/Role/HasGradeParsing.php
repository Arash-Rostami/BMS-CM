<?php

namespace App\Models\Traits\Role;

use Illuminate\Support\Str;

trait HasGradeParsing
{
    private static array $grades = ['junior', 'mid', 'senior'];
    private static array $gradeLabels = ['junior' => '⭐', 'mid' => '⭐⭐', 'senior' => '⭐⭐⭐'];

    public function getBaseNameAttribute(): string
    {
        return self::extractBaseName($this->name);
    }

    public function getGradeAttribute(): ?string
    {
        return self::extractGrade($this->name);
    }

    public function getGradeLabelAttribute(): ?string
    {
        return self::$gradeLabels[$this->grade] ?? null;
    }

    public function getGradeTitleAttribute(): ?string
    {
        $grade = $this->grade;
        return $grade ? (self::$gradeLabels[$grade] . ' ' . ucfirst($grade)) : $grade;
    }

    public static function extractBaseName(string $name): string
    {
        $parts = explode('_', $name);
        if (in_array(end($parts), self::$grades, true)) {
            array_pop($parts);
        }
        return implode('_', $parts);
    }

    public static function extractGrade(string $name): ?string
    {
        $parts = explode('_', $name);
        $last = end($parts);
        return in_array($last, self::$grades, true) ? $last : null;
    }

    public static function combineName(string $baseName, ?string $grade): string
    {
        $base = self::extractBaseName($baseName);
        return $grade ? "{$base}_{$grade}" : $base;
    }

    public static function getGradeOptions(): array
    {
        return self::$gradeLabels;
    }

    public static function normalizeToSnake(string $name): string
    {
        return Str::snake($name);
    }

    public function scopeWhereBaseName($query, string $baseName)
    {
        return $query->where(fn($q) => $q->where('name', $baseName)->orWhere('name', 'like', "{$baseName}_%"));
    }

    public function scopeWhereGrade($query, string $grade)
    {
        return $query->where('name', 'like', "%_{$grade}");
    }

    public static function getUniqueBaseNames(): array
    {
        return static::all()
            ->mapWithKeys(function ($role) {
                $baseName = $role->base_name;
                $key = "resources/user/strings.general.options.{$baseName}";
                $translation = __($key);
                $label = $translation !== $key ? $translation : Str::title(str_replace('_', ' ', $baseName));
                return [$baseName => $label];
            })
            ->unique()
            ->sort()
            ->all();
    }
}
