<?php
namespace App\Models;

use App\Models\Traits\Role\HasGradeParsing;
use App\Models\Traits\Role\HasPermissionGrouping;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasGradeParsing, HasPermissionGrouping;

    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.role_pivot_key') ?: 'role_id',
            config('permission.column_names.model_morph_key') ?: 'model_id'
        );
    }
}
