<?php

namespace Tests\Feature\Services;

use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchServiceTest extends TestCase
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

        $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_empty_response_marks_every_pipeline_stage_upcoming(): void
    {
        $response = app(SearchService::class)->emptyResponse();

        $this->assertSame([], $response['results']);
        $this->assertNull($response['by_user']);
        $this->assertSame(
            ['purchaseRequest', 'proformaInvoice', 'purchaseOrder', 'registeredOrder', 'bankProfile', 'payment', 'shipment', 'custom'],
            array_keys($response['breadcrumb'])
        );
        $this->assertTrue(collect($response['breadcrumb'])->every(fn ($stage) => $stage['state'] === 'upcoming'));
    }

    public function test_search_finds_a_record_by_its_identifier_for_an_authorized_user(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $request = PurchaseRequest::factory()->create();
        $term = 'FIND'.strtoupper(uniqid());
        PurchaseRequest::whereKey($request->id)->update(['pr_number' => $term]);

        $response = app(SearchService::class)->search($term);

        $this->assertCount(1, $response['results']);
        $this->assertSame('purchaseRequest', $response['results'][0]['type']);
        $this->assertSame($request->id, $response['results'][0]['id']);
        $this->assertSame('completed', $response['breadcrumb']['purchaseRequest']['state']);
    }

    public function test_search_hides_models_the_user_cannot_view(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = PurchaseRequest::factory()->create();
        $term = 'FIND'.strtoupper(uniqid());
        PurchaseRequest::whereKey($request->id)->update(['pr_number' => $term]);

        $response = app(SearchService::class)->search($term);

        $this->assertSame([], $response['results']);
    }

    public function test_search_escapes_like_wildcards_instead_of_matching_everything(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $response = app(SearchService::class)->search('%_');

        $this->assertSame([], $response['results']);
        $this->assertTrue(collect($response['breadcrumb'])->every(fn ($stage) => $stage['state'] === 'upcoming'));
    }

    public function test_search_resolves_by_user_when_the_term_matches_a_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Zubair Wick '.uniqid()]);
        $this->actingAs(User::factory()->create());

        $response = app(SearchService::class)->search($user->name);

        $this->assertSame(['id' => $user->id, 'name' => $user->name], $response['by_user']);
        $this->assertSame([], $response['results']);
    }

    public function test_chain_returns_an_empty_response_for_an_unknown_type(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $response = app(SearchService::class)->chain('notAType', 1);

        $this->assertSame(['anchor' => null, 'chain' => []], $response);
    }

    public function test_chain_returns_an_empty_response_when_the_user_cannot_view_the_anchor_type(): void
    {
        $this->actingAsUserWithPermissions(['payment.view']);

        $response = app(SearchService::class)->chain('purchaseRequest', 1);

        $this->assertSame(['anchor' => null, 'chain' => []], $response);
    }

    public function test_chain_returns_an_empty_response_for_a_missing_record(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $response = app(SearchService::class)->chain('purchaseRequest', 999999999);

        $this->assertSame(['anchor' => null, 'chain' => []], $response);
    }

    public function test_chain_walks_the_pipeline_from_a_registered_order_anchor(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);

        $order = RegisteredOrder::factory()->create();

        $response = app(SearchService::class)->chain('registeredOrder', $order->id);

        $this->assertSame(['type' => 'registeredOrder', 'id' => $order->id], $response['anchor']);
        $this->assertCount(8, $response['chain']);

        $entries = collect($response['chain'])->keyBy('key');
        $this->assertTrue($entries['registeredOrder']['attached']);
        $this->assertSame($order->ro_number, $entries['registeredOrder']['records'][0]['identifier']);
        $this->assertFalse($entries['purchaseRequest']['attached']);
        $this->assertSame('completed', $response['breadcrumb']['registeredOrder']['state']);
        $this->assertSame('missing', $response['breadcrumb']['purchaseRequest']['state']);
    }

    public function test_chain_hides_record_data_of_stages_the_user_cannot_view(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);

        $order = RegisteredOrder::factory()->create();

        $response = app(SearchService::class)->chain('registeredOrder', $order->id);

        $entries = collect($response['chain'])->keyBy('key');
        $this->assertNotEmpty($entries['registeredOrder']['records']);
        $this->assertSame([], $entries['shipment']['records']);
    }
}