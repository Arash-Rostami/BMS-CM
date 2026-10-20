<?php

namespace Tests\Feature\Http;

use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    private const EMPTY_CHAIN = ['anchor' => null, 'chain' => []];

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

        $role = Role::create(['name' => 'search_controller_test_role_'.uniqid(), 'guard_name' => 'web']);
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

    public function test_both_endpoints_require_a_signed_in_user(): void
    {
        $this->getJson(route('search.spotlight', ['q' => 'anything']))->assertUnauthorized();
        $this->getJson(route('search.chain', ['type' => 'purchaseRequest', 'id' => 1]))->assertUnauthorized();
    }

    #[DataProvider('shortTerms')]
    public function test_spotlight_answers_the_empty_shape_without_searching_for_a_missing_or_one_character_term(array $query): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $this->request('A');

        $this->getJson(route('search.spotlight', $query))
            ->assertOk()
            ->assertJsonPath('results', [])
            ->assertJsonPath('by_user', null)
            ->assertJsonCount(8, 'breadcrumb')
            ->assertJsonPath('breadcrumb.purchaseRequest.state', 'upcoming');
    }

    public static function shortTerms(): array
    {
        return ['missing' => [[]], 'empty' => [['q' => '']], 'one char' => [['q' => 'A']], 'padded one char' => [['q' => '  A  ']], 'spaces' => [['q' => '   ']]];
    }

    public function test_spotlight_finds_a_viewable_record_by_identifier_and_trims_the_term(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $term = 'HTTP'.strtoupper(uniqid());
        $request = $this->request($term);

        $this->getJson(route('search.spotlight', ['q' => "  {$term}  "]))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.type', 'purchaseRequest')
            ->assertJsonPath('results.0.id', $request->id)
            ->assertJsonPath('breadcrumb.purchaseRequest.state', 'completed');
    }

    public function test_spotlight_returns_nothing_for_a_module_the_user_cannot_view(): void
    {
        $this->actingAsUserWithPermissions(['payment.view']);
        $term = 'HTTP'.strtoupper(uniqid());
        $this->request($term);

        $this->getJson(route('search.spotlight', ['q' => $term]))
            ->assertOk()
            ->assertJsonPath('results', []);
    }

    public function test_spotlight_treats_like_wildcards_literally(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $this->request('HTTP'.strtoupper(uniqid()));

        $this->getJson(route('search.spotlight', ['q' => '%_']))
            ->assertOk()
            ->assertJsonPath('results', []);
    }

    public function test_spotlight_survives_a_non_string_term_without_a_server_error(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $this->getJson(route('search.spotlight').'?q[]=abc')->assertOk();
    }

    #[DataProvider('invalidChainParameters')]
    public function test_chain_answers_the_empty_shape_for_missing_or_invalid_parameters(array $query): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);

        $this->getJson(route('search.chain', $query))->assertOk()->assertExactJson(self::EMPTY_CHAIN);
    }

    public static function invalidChainParameters(): array
    {
        return [
            'nothing' => [[]],
            'type only' => [['type' => 'registeredOrder']],
            'id only' => [['id' => 5]],
            'blank type' => [['type' => '', 'id' => 5]],
            'zero id' => [['type' => 'registeredOrder', 'id' => 0]],
            'negative id' => [['type' => 'registeredOrder', 'id' => -3]],
            'text id' => [['type' => 'registeredOrder', 'id' => 'abc']],
            'array type' => [['type' => ['x'], 'id' => 5]],
        ];
    }

    public function test_chain_answers_the_empty_shape_for_an_unknown_type(): void
    {
        $order = RegisteredOrder::factory()->create();
        $this->actingAsUserWithPermissions(['registered_order.view']);

        $this->getJson(route('search.chain', ['type' => 'notAType', 'id' => $order->id]))->assertOk()->assertExactJson(self::EMPTY_CHAIN);
    }

    public function test_chain_answers_the_empty_shape_for_a_missing_record(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);

        $this->getJson(route('search.chain', ['type' => 'registeredOrder', 'id' => 999999999]))->assertOk()->assertExactJson(self::EMPTY_CHAIN);
    }

    public function test_chain_answers_the_empty_shape_when_the_user_cannot_view_the_anchor_module(): void
    {
        $order = RegisteredOrder::factory()->create();
        $this->actingAsUserWithPermissions(['payment.view']);

        $this->getJson(route('search.chain', ['type' => 'registeredOrder', 'id' => $order->id]))->assertOk()->assertExactJson(self::EMPTY_CHAIN);
    }

    public function test_chain_returns_the_pipeline_around_a_viewable_anchor_and_hides_stages_the_user_cannot_view(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view']);
        $order = RegisteredOrder::factory()->create();

        $response = $this->getJson(route('search.chain', ['type' => 'registeredOrder', 'id' => $order->id]))
            ->assertOk()
            ->assertJsonPath('anchor', ['type' => 'registeredOrder', 'id' => $order->id])
            ->assertJsonCount(8, 'chain');

        $entries = collect($response->json('chain'))->keyBy('key');
        $this->assertTrue($entries['registeredOrder']['attached']);
        $this->assertSame($order->ro_number, $entries['registeredOrder']['records'][0]['identifier']);
        $this->assertSame([], $entries['shipment']['records']);
    }
}
