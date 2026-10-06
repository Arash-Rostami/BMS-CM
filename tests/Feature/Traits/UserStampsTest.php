<?php

namespace Tests\Feature\Traits;

use App\Models\Bank;
use App\Models\Traits\General\UserStamps;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Models\Traits\General\UserStamps auto-stamps user_id on creating and
 * updated_by_id on updating (both auth()-gated, update additionally isDirty()-gated).
 * Composed on 22 unrelated models — a genuine cross-cutting mechanism. The resource
 * test suite already exercises the "force-overwrites whatever a factory passed"
 * consequence (testPattern.md §0) as a side effect of other tests, but nothing
 * asserts the trait's own three branches directly: authenticated create, unauthenticated
 * create (no stamp), and update-only-when-dirty. This file is that direct test.
 */
class UserStampsTest extends TestCase
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

    public function test_bank_composes_the_trait(): void
    {
        $this->assertContains(UserStamps::class, class_uses_recursive(Bank::class));
    }

    public function test_creating_while_authenticated_stamps_user_id_to_the_acting_user(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $bank = Bank::factory()->create(['user_id' => null]);

        $this->assertSame($actor->id, $bank->user_id);
    }

    public function test_creating_without_authentication_leaves_user_id_untouched(): void
    {
        $bank = Bank::factory()->create(['user_id' => null]);

        $this->assertNull($bank->user_id);
    }

    public function test_updating_while_authenticated_stamps_updated_by_id_only_when_the_model_is_dirty(): void
    {
        $creator = User::factory()->create();
        $editor = User::factory()->create();
        $bank = Bank::factory()->create(['user_id' => $creator->id, 'updated_by_id' => null]);

        $this->actingAs($editor);
        $bank->description = $bank->description.' (edited)';
        $bank->save();

        $this->assertSame($editor->id, $bank->updated_by_id);
    }

    public function test_saving_an_unchanged_model_does_not_stamp_updated_by_id(): void
    {
        $creator = User::factory()->create();
        $editor = User::factory()->create();
        $bank = Bank::factory()->create(['user_id' => $creator->id, 'updated_by_id' => null]);

        $this->actingAs($editor);
        $bank->save();

        $this->assertNull($bank->updated_by_id);
    }
}
