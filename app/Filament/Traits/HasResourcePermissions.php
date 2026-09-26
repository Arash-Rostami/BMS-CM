<?php

namespace App\Filament\Traits;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasResourcePermissions
{
    /**
     * Derives the singular snake_case permission prefix from the model class name, ie ProformaInvoice → proforma_invoice
     */
    public static function getPermissionPrefix(): string
    {
        return Str::snake(class_basename(static::getModel()));
    }

    private static function allows(string $action): bool
    {
        return auth()->user()?->can(static::getPermissionPrefix().'.'.$action) ?? false;
    }

    private static function allowsResponse(string $action): Response
    {
        return static::allows($action) ? Response::allow() : Response::deny();
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return static::allowsResponse('view');
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return static::allowsResponse('view');
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return static::allowsResponse('create');
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::allowsResponse('edit');
    }

    public static function getUpdateAuthorizationResponse(Model $record): Response
    {
        return static::allowsResponse('edit');
    }

    public static function getAttachAuthorizationResponse(): Response
    {
        return static::allowsResponse('edit');
    }

    public static function getDetachAuthorizationResponse(Model $record): Response
    {
        return static::allowsResponse('edit');
    }

    public static function getDetachAnyAuthorizationResponse(): Response
    {
        return static::allowsResponse('edit');
    }

    public static function getAssociateAuthorizationResponse(): Response
    {
        return static::allowsResponse('edit');
    }

    public static function getDissociateAuthorizationResponse(Model $record): Response
    {
        return static::allowsResponse('edit');
    }

    public static function getDissociateAnyAuthorizationResponse(): Response
    {
        return static::allowsResponse('edit');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::allowsResponse('delete');
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return static::allowsResponse('delete');
    }

    public static function getForceDeleteAuthorizationResponse(Model $record): Response
    {
        return static::allowsResponse('delete');
    }

    public static function getForceDeleteAnyAuthorizationResponse(): Response
    {
        return static::allowsResponse('delete');
    }

    public static function getRestoreAuthorizationResponse(Model $record): Response
    {
        return static::allowsResponse('restore');
    }

    public static function getRestoreAnyAuthorizationResponse(): Response
    {
        return static::allowsResponse('restore');
    }

    public static function canViewAny(): bool
    {
        return static::allows('view');
    }

    public static function canView($record): bool
    {
        return static::allows('view');
    }

    public static function canCreate(): bool
    {
        return static::allows('create');
    }

    public static function canEdit($record): bool
    {
        return static::allows('edit');
    }

    public static function canEditAny(): bool
    {
        return static::allows('edit');
    }

    public static function canDelete($record): bool
    {
        return static::allows('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::allows('delete');
    }

    public static function canForceDelete($record): bool
    {
        return static::allows('delete');
    }

    public static function canForceDeleteAny(): bool
    {
        return static::allows('delete');
    }

    public static function canRestore($record): bool
    {
        return static::allows('restore');
    }

    public static function canRestoreAny(): bool
    {
        return static::allows('restore');
    }
}
