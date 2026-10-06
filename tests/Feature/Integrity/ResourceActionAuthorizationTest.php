<?php

namespace Tests\Feature\Integrity;

use App\Filament\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ResourceActionAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('permissions', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_primary');
        });

        Schema::create('model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_primary');
        });

        Schema::create('role_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_primary');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function actingAsUserWithPermissions(array $permissionNames): User
    {
        $user = User::forceCreate([
            'name' => 'Test User',
            'email' => uniqid().'@example.com',
            'password' => 'secret',
        ]);

        $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }

        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_view_only_permission_denies_edit_delete_and_restore_authorization(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $record = new PurchaseRequest;

        $this->assertTrue(PurchaseRequestResource::getViewAuthorizationResponse($record)->allowed());
        $this->assertFalse(PurchaseRequestResource::getEditAuthorizationResponse($record)->allowed());
        $this->assertFalse(PurchaseRequestResource::getDeleteAuthorizationResponse($record)->allowed());
        $this->assertFalse(PurchaseRequestResource::getDeleteAnyAuthorizationResponse()->allowed());
        $this->assertFalse(PurchaseRequestResource::getRestoreAuthorizationResponse($record)->allowed());
    }

    public function test_delete_permission_grants_delete_authorization_only(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.delete']);

        $record = new PurchaseRequest;

        $this->assertTrue(PurchaseRequestResource::getDeleteAuthorizationResponse($record)->allowed());
        $this->assertFalse(PurchaseRequestResource::getEditAuthorizationResponse($record)->allowed());
    }

    public function test_no_permissions_denies_every_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = new PurchaseRequest;

        $this->assertFalse(PurchaseRequestResource::getViewAuthorizationResponse($record)->allowed());
        $this->assertFalse(PurchaseRequestResource::getEditAuthorizationResponse($record)->allowed());
        $this->assertFalse(PurchaseRequestResource::getDeleteAuthorizationResponse($record)->allowed());
        $this->assertFalse(PurchaseRequestResource::getRestoreAuthorizationResponse($record)->allowed());
    }

    public function test_edit_page_blocks_access_to_trashed_records(): void
    {
        $method = (new \ReflectionClass(\App\Filament\Pages\EditRecord::class))->getMethod('authorizeAccess');

        $this->assertSame(
            \App\Filament\Pages\EditRecord::class,
            $method->getDeclaringClass()->getName(),
            'EditRecord no longer overrides authorizeAccess() — a trashed record\'s /edit URL would silently become editable again'
        );

        $source = file_get_contents((new \ReflectionClass(\App\Filament\Pages\EditRecord::class))->getFileName());

        $this->assertStringContainsString(
            'trashed()',
            $source,
            'authorizeAccess() no longer checks trashed() — a soft-deleted record could be edited via direct URL access'
        );
    }
}
