<?php

namespace App\Models\Traits\CalendarRule;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility;
use App\Filament\Resources\Master\UserResource\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

trait HasVisibility
{
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $roleIds = $user->roles->pluck('id');

        return $query->where(function (Builder $query) use ($user, $roleIds) {
            $query->where('visibility', Visibility::EVERYONE->value)
                ->orWhere('user_id', $user->id)
                ->orWhere(function (Builder $query) use ($user) {
                    $query->where('visibility', Visibility::USERS->value)
                        ->where(function (Builder $query) use ($user) {
                            $query->whereJsonContains('shared_user_ids', $user->id)
                                ->orWhereJsonContains('shared_user_ids', (string) $user->id);
                        });
                })
                ->when($roleIds->isNotEmpty(), fn (Builder $query) => $query->orWhere(function (Builder $query) use ($roleIds) {
                    $query->where('visibility', Visibility::ROLES->value)
                        ->where(function (Builder $query) use ($roleIds) {
                            foreach ($roleIds as $roleId) {
                                $query->orWhereJsonContains('shared_role_ids', $roleId)
                                    ->orWhereJsonContains('shared_role_ids', (string) $roleId);
                            }
                        });
                }));
        });
    }

    public function isEditableBy(User $user): bool
    {
        return $user->isAdmin() || (int) $this->user_id === $user->id;
    }

    public function recipients(): Collection
    {
        return User::query()
            ->where(fn (Builder $query) => match ($this->visibility) {
                Visibility::EVERYONE => $query->whereRaw('1 = 1'),
                Visibility::ME => $query->whereKey($this->user_id),
                Visibility::USERS => $query->whereKey($this->user_id)->orWhereIn('id', $this->shared_user_ids ?? []),
                Visibility::ROLES => $query->whereHas('roles', fn (Builder $roles) => $roles->whereIn('roles.id', $this->shared_role_ids ?? [])),
            })
            ->permission(Str::snake(class_basename($this->subject)).'.view')
            ->whereNot('status', UserStatus::INACTIVE->value)
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function outsideEmails(): array
    {
        return $this->shouldSendEmail() ? ($this->notify_emails ?? []) : [];
    }
}
