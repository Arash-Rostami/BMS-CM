<?php

namespace App\Filament\Traits;

use Illuminate\Support\Str;

trait HasResourcePermissions
{
    /**
     * Derives the singular snake_case permission prefix from the model class name.
     * Bank → bank, BankProfile → bank_profile, ProformaInvoice → proforma_invoice
     */
    public static function getPermissionPrefix(): string
    {
        return Str::snake(class_basename(static::getModel()));
    }

    private static function allows(string $action): bool
    {
        return auth()->user()?->can(static::getPermissionPrefix() . '.' . $action) ?? false;
    }

    public static function canViewAny(): bool       { return static::allows('view'); }
    public static function canView($record): bool   { return static::allows('view'); }
    public static function canCreate(): bool        { return static::allows('create'); }
    public static function canEdit($record): bool   { return static::allows('edit'); }
    public static function canDelete($record): bool { return static::allows('delete'); }
    public static function canDeleteAny(): bool     { return static::allows('delete'); }
    public static function canForceDelete($record): bool { return static::allows('delete'); }
    public static function canForceDeleteAny(): bool     { return static::allows('delete'); }
    public static function canRestore($record): bool     { return static::allows('edit'); }
    public static function canRestoreAny(): bool         { return static::allows('edit'); }
}
