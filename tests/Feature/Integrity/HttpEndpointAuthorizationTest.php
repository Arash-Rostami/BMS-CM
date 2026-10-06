<?php

namespace Tests\Feature\Integrity;

use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HttpEndpointAuthorizationTest extends TestCase
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

    public function test_guests_are_redirected_from_the_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
    }

    public function test_user_can_helper_matches_the_model_specific_permission(): void
    {
        $this->actingAsUserWithPermissions(['shipment.view']);

        $this->assertTrue(userCan(Shipment::class));
        $this->assertFalse(userCan(PurchaseRequest::class));
        $this->assertFalse(userCan(Shipment::class, 'edit'));
    }

    public function test_user_can_helper_denies_a_guest(): void
    {
        $this->assertFalse(userCan(Shipment::class));
    }

    public function test_endpoints_known_to_leak_cross_model_data_call_user_can(): void
    {
        $violations = [];
        $expected = [
            app_path('Http/Controllers/InvoiceController.php') => 'userCan(Shipment::class)',
            app_path('Services/SearchService.php') => 'userCan(',
        ];

        foreach ($expected as $file => $needle) {
            if (! str_contains(file_get_contents($file), $needle)) {
                $violations[] = basename($file).' lost its userCan() gate — any authenticated user could read/download cross-model data again';
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }
}
