<?php

namespace Tests\Feature\Http;

use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkspaceControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function useMysql(): void
    {
        $env = base_path('.env');
        if (is_file($env)) {
            $vals = [];
            foreach (explode("\n", (string) file_get_contents($env)) as $line) {
                if (preg_match('/^\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/', $line, $m)) {
                    $vals[$m[1]] = trim(preg_replace('/\s+#.*$/', '', trim($m[2])), "\"' \t");
                }
            }
            $map = ['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_DATABASE' => 'database', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'];
            foreach ($map as $envKey => $cfgKey) {
                if (isset($vals[$envKey])) {
                    config(['database.connections.mysql.'.$cfgKey => $vals[$envKey]]);
                }
            }
        }
        DB::purge('mysql');
        config(['database.default' => 'mysql']);
    }

    private function actingAsUserWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create();

        $role = Role::create(['name' => 'workspace_controller_test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        return $user;
    }

    private function request(string $number): PurchaseRequest
    {
        $request = PurchaseRequest::factory()->create();
        PurchaseRequest::whereKey($request->id)->update(['pr_number' => $number]);

        return $request;
    }

    public function test_the_endpoint_requires_a_signed_in_user(): void
    {
        $this->getJson(route('workspace.records', ['resource' => 'purchaseRequests']))->assertUnauthorized();
    }

    #[DataProvider('foreignResources')]
    public function test_a_resource_outside_the_whitelist_is_a_404_even_for_a_signed_in_user(string $resource): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $this->getJson(route('workspace.records', ['resource' => $resource]))->assertNotFound();
    }

    public static function foreignResources(): array
    {
        return array_map(fn (string $resource): array => [$resource], ['users', 'permissions', 'notAResource', '..%2Fetc', 'PurchaseRequests']);
    }

    public function test_a_user_without_the_view_permission_of_that_module_gets_a_403(): void
    {
        $this->actingAsUserWithPermissions(['payment.view']);

        $this->getJson(route('workspace.records', ['resource' => 'purchaseRequests']))->assertForbidden();
    }

    public function test_it_returns_the_matching_record_as_a_pin_row_under_the_data_key(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $term = 'WSHTTP'.strtoupper(uniqid());
        $request = $this->request($term);

        $this->getJson(route('workspace.records', ['resource' => 'purchaseRequests', 'q' => $term]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'purchaseRequests:'.$request->id)
            ->assertJsonPath('data.0.resourceId', 'purchaseRequests')
            ->assertJsonPath('data.0.recordId', $request->id)
            ->assertJsonPath('data.0.label', $term)
            ->assertJsonStructure(['data' => [['key', 'resourceId', 'recordId', 'label', 'subtitle', 'url', 'status']]]);
    }

    public function test_the_row_url_is_the_edit_page_for_an_editor(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'purchase_request.edit']);
        $term = 'WSHTTP'.strtoupper(uniqid());
        $request = $this->request($term);

        $url = $this->getJson(route('workspace.records', ['resource' => 'purchaseRequests', 'q' => $term]))->json('data.0.url');

        $this->assertSame(route('filament.dashboard.resources.purchase-requests.edit', ['record' => $request->id]), $url);
    }

    public function test_the_row_url_is_the_index_page_for_a_view_only_user(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $term = 'WSHTTP'.strtoupper(uniqid());
        $this->request($term);

        $url = $this->getJson(route('workspace.records', ['resource' => 'purchaseRequests', 'q' => $term]))->json('data.0.url');

        $this->assertSame(route('filament.dashboard.resources.purchase-requests.index'), $url);
    }

    public function test_like_wildcards_in_the_term_are_matched_literally(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $this->request('WSHTTP'.strtoupper(uniqid()));

        $this->getJson(route('workspace.records', ['resource' => 'purchaseRequests', 'q' => '%_']))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    #[DataProvider('blankTerms')]
    public function test_a_blank_or_missing_term_lists_at_most_the_25_newest_records_newest_first(array $query): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $newest = $this->request('WSHTTP'.strtoupper(uniqid()));

        $data = $this->getJson(route('workspace.records', ['resource' => 'purchaseRequests'] + $query))
            ->assertOk()
            ->json('data');

        $this->assertLessThanOrEqual(25, count($data));
        $this->assertSame($newest->id, $data[0]['recordId']);
    }

    public static function blankTerms(): array
    {
        return ['missing' => [[]], 'empty' => [['q' => '']], 'spaces' => [['q' => '   ']]];
    }

    public function test_a_non_string_term_does_not_cause_a_server_error(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $this->getJson(route('workspace.records', ['resource' => 'purchaseRequests']).'?q[]=abc')->assertOk();
    }
}
