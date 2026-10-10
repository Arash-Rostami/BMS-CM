<?php

use App\Models\Permission;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

$names = array_map(fn (string $action) => "calendar_rule.{$action}", ['view', 'create', 'edit', 'delete', 'restore']);

$permissions = collect($names)->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));

foreach (['admin_junior', 'admin_senior'] as $roleName) {
    Role::where('name', $roleName)->first()?->givePermissionTo($permissions);
}

app(PermissionRegistrar::class)->forgetCachedPermissions();

echo 'calendar_rule permissions ready: '.implode(', ', $names).PHP_EOL;
