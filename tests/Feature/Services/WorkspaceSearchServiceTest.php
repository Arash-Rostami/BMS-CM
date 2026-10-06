<?php

namespace Tests\Feature\Services;

use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\WorkspaceSearchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class WorkspaceSearchServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        Cache::forget('workspace_columns:default:purchase_requests');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Cache::forget('workspace_columns:default:purchase_requests');
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

        $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_search_aborts_404_for_a_resource_outside_the_whitelist(): void
    {
        $this->expectException(NotFoundHttpException::class);

        app(WorkspaceSearchService::class)->search('notAResource', 'term');
    }

    public function test_search_aborts_403_without_an_authenticated_user(): void
    {
        try {
            app(WorkspaceSearchService::class)->search('purchaseRequests', 'term');
            $this->fail('Expected the workspace search to be denied.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_search_aborts_403_without_the_view_permission(): void
    {
        $this->actingAs(User::factory()->create());

        try {
            app(WorkspaceSearchService::class)->search('purchaseRequests', 'term');
            $this->fail('Expected the workspace search to be denied.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_search_returns_the_matching_record_with_the_pin_key_and_edit_url(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $request = PurchaseRequest::factory()->create();
        $term = 'PIN'.strtoupper(uniqid());
        PurchaseRequest::whereKey($request->id)->update(['pr_number' => $term]);

        $results = app(WorkspaceSearchService::class)->search('purchaseRequests', $term);

        $this->assertCount(1, $results);
        $this->assertSame('purchaseRequests:'.$request->id, $results[0]['key']);
        $this->assertSame('purchaseRequests', $results[0]['resourceId']);
        $this->assertSame($request->id, $results[0]['recordId']);
        $this->assertSame($term, $results[0]['label']);
        $this->assertSame(
            route('filament.dashboard.resources.purchase-requests.edit', ['record' => $request->id]),
            $results[0]['url']
        );
    }

    public function test_search_with_an_empty_term_lists_recent_records_first(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $request = PurchaseRequest::factory()->create();

        $results = app(WorkspaceSearchService::class)->search('purchaseRequests', '');

        $this->assertSame($request->id, $results[0]['recordId']);
        $this->assertLessThanOrEqual(25, count($results));
    }
}