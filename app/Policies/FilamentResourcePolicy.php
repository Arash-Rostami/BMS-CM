<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class FilamentResourcePolicy
{
    /**
     * Store the model class if we have to resort to debug backtrace or similar, but typically Gate passes it.
     */
    protected function extractModelClass(array $args, $default = null)
    {
        foreach ($args as $arg) {
            if (is_string($arg) && str_starts_with($arg, 'App\\Models\\')) {
                return $arg;
            }
            if ($arg instanceof Model) {
                return $arg;
            }
        }

        return $default;
    }

    protected function getPermissionPrefix(Model|string $model): string
    {
        $className = is_string($model) ? class_basename($model) : class_basename(get_class($model));

        return Str::snake($className);
    }

    protected function checkPermission(User $user, Model|string $model, string $action): bool
    {
        if (! $model || $model === self::class) {
            // fallback: attempt to extract model from route (Filament does this sometimes)
            $model = request()->route('model') ?? 'Dummy';
        }

        $permission = $this->getPermissionPrefix($model).'.'.$action;

        try {
            return $user->hasPermissionTo($permission) || $user->can($permission);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    public function viewAny(User $user, ?string $modelClass = null): bool
    {
        $args = func_get_args();

        return $this->checkPermission($user, $this->extractModelClass($args, static::class), 'view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->checkPermission($user, $model, 'view');
    }

    public function create(User $user, ?string $modelClass = null): bool
    {
        $args = func_get_args();

        return $this->checkPermission($user, $this->extractModelClass($args, static::class), 'create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->checkPermission($user, $model, 'edit');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->checkPermission($user, $model, 'delete');
    }

    public function deleteAny(User $user, ?string $modelClass = null): bool
    {
        $args = func_get_args();

        return $this->checkPermission($user, $this->extractModelClass($args, static::class), 'delete');
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->checkPermission($user, $model, 'restore');
    }

    public function restoreAny(User $user, ?string $modelClass = null): bool
    {
        $args = func_get_args();

        return $this->checkPermission($user, $this->extractModelClass($args, static::class), 'restore');
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->checkPermission($user, $model, 'delete');
    }

    public function forceDeleteAny(User $user, ?string $modelClass = null): bool
    {
        $args = func_get_args();

        return $this->checkPermission($user, $this->extractModelClass($args, static::class), 'delete');
    }
}
