<?php

namespace Tests\Feature\Models;

use App\Models\Correspondence;
use App\Models\RegisteredOrder;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CorrespondenceModelTest extends TestCase
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

    private function correspondenceStatus(string $englishName): Status
    {
        return Status::factory()->create([
            'type' => Correspondence::TYPE_CORRESPONDENCE_STATUS,
            'english_type' => Correspondence::TYPE_CORRESPONDENCE_STATUS,
            'name' => $englishName,
            'english_name' => $englishName,
        ]);
    }

    // status() relation — scoped to Correspondence Status type

    public function test_status_relation_is_scoped_to_the_correspondence_status_type(): void
    {
        $wrongType = Status::factory()->create(['english_type' => 'Purchase Order Status']);
        $correspondence = Correspondence::factory()->create(['status_id' => $wrongType->id]);

        $this->assertNull($correspondence->fresh()->status);
    }

    public function test_status_relation_resolves_a_matching_correspondence_status(): void
    {
        $status = $this->correspondenceStatus('Submitted');
        $correspondence = Correspondence::factory()->create(['status_id' => $status->id]);

        $this->assertSame($status->id, $correspondence->fresh()->status?->id);
    }

    // conversationId accessor

    public function test_conversation_id_resolves_to_its_own_id_for_a_root_correspondence(): void
    {
        $root = Correspondence::factory()->create(['parent_id' => null]);

        $this->assertSame($root->id, $root->conversation_id);
    }

    public function test_conversation_id_resolves_to_the_parent_id_for_a_reply(): void
    {
        $root = Correspondence::factory()->create(['parent_id' => null]);
        $reply = Correspondence::factory()->create(['parent_id' => $root->id]);

        $this->assertSame($root->id, $reply->conversation_id);
    }

    // markReadBy — idempotent, scoped to the given user's own pivot row

    public function test_mark_read_by_sets_read_at_when_currently_unread(): void
    {
        $user = User::factory()->create();
        $correspondence = Correspondence::factory()->create();
        $correspondence->recipients()->attach($user->id, ['type' => 'to', 'read_at' => null]);

        $correspondence->markReadBy($user->id);

        $pivot = $correspondence->recipients()->wherePivot('user_id', $user->id)->first()->pivot;
        $this->assertNotNull($pivot->read_at);
    }

    public function test_mark_read_by_does_not_rewrite_an_already_read_pivot(): void
    {
        $user = User::factory()->create();
        $correspondence = Correspondence::factory()->create();
        $readAt = now()->subDay();
        $correspondence->recipients()->attach($user->id, ['type' => 'to', 'read_at' => $readAt]);

        $before = $correspondence->recipients()->wherePivot('user_id', $user->id)->first()->pivot->read_at;

        $correspondence->markReadBy($user->id);

        $after = $correspondence->recipients()->wherePivot('user_id', $user->id)->first()->pivot->read_at;

        $this->assertTrue($before->equalTo($after));
    }

    public function test_mark_read_by_only_affects_the_given_users_own_pivot_row(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $correspondence = Correspondence::factory()->create();
        $correspondence->recipients()->attach($userA->id, ['type' => 'to', 'read_at' => null]);
        $correspondence->recipients()->attach($userB->id, ['type' => 'to', 'read_at' => null]);

        $correspondence->markReadBy($userA->id);

        $pivotA = $correspondence->recipients()->wherePivot('user_id', $userA->id)->first()->pivot;
        $pivotB = $correspondence->recipients()->wherePivot('user_id', $userB->id)->first()->pivot;

        $this->assertNotNull($pivotA->read_at);
        $this->assertNull($pivotB->read_at);
    }

    // HasThreadGroup — thread key/title/scoping resolve to the root, not the clicked reply

    public function test_get_thread_key_resolves_to_its_own_id_for_a_root_correspondence(): void
    {
        $root = Correspondence::factory()->create(['parent_id' => null]);

        $this->assertSame((string) $root->id, $root->getThreadKey());
    }

    public function test_get_thread_key_resolves_a_replys_key_to_the_root_id(): void
    {
        $root = Correspondence::factory()->create(['parent_id' => null]);
        $reply = Correspondence::factory()->create(['parent_id' => $root->id]);

        $this->assertSame((string) $root->id, $reply->getThreadKey());
    }

    public function test_get_thread_title_includes_the_root_subject_and_unique_sender_names(): void
    {
        $creator = User::factory()->create(['name' => 'Thread Creator']);
        $this->actingAs($creator);
        $root = Correspondence::factory()->create(['parent_id' => null, 'subject' => 'Original Subject']);
        Correspondence::factory()->create(['parent_id' => $root->id, 'subject' => 'Re: Original Subject']);

        $title = $root->fresh()->getThreadTitle();

        $this->assertStringContainsString('Original Subject', $title);
        $this->assertSame(1, substr_count($title, 'Thread Creator'));
    }

    public function test_scope_thread_query_returns_only_the_root_and_its_replies(): void
    {
        $root = Correspondence::factory()->create(['parent_id' => null]);
        $reply = Correspondence::factory()->create(['parent_id' => $root->id]);
        $unrelated = Correspondence::factory()->create(['parent_id' => null]);

        $results = Correspondence::scopeThreadQuery(Correspondence::query(), (string) $root->id)->pluck('id');

        $this->assertTrue($results->contains($root->id));
        $this->assertTrue($results->contains($reply->id));
        $this->assertFalse($results->contains($unrelated->id));
    }

    // Relationships

    public function test_correspondable_resolves_the_polymorphic_owning_record(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $correspondence = Correspondence::factory()->forCorrespondable($ro)->create();

        $this->assertTrue($correspondence->fresh()->correspondable->is($ro));
    }

    public function test_replies_relation_returns_only_direct_children(): void
    {
        $root = Correspondence::factory()->create(['parent_id' => null]);
        $reply = Correspondence::factory()->create(['parent_id' => $root->id]);
        $unrelated = Correspondence::factory()->create(['parent_id' => null]);

        $replyIds = $root->fresh()->replies->pluck('id');

        $this->assertTrue($replyIds->contains($reply->id));
        $this->assertFalse($replyIds->contains($unrelated->id));
    }

    public function test_unread_recipients_relation_excludes_recipients_who_have_already_read(): void
    {
        $unreadUser = User::factory()->create();
        $readUser = User::factory()->create();
        $correspondence = Correspondence::factory()->create();
        $correspondence->recipients()->attach($unreadUser->id, ['type' => 'to', 'read_at' => null]);
        $correspondence->recipients()->attach($readUser->id, ['type' => 'to', 'read_at' => now()]);

        $unreadIds = $correspondence->unreadRecipients()->pluck('users.id');

        $this->assertTrue($unreadIds->contains($unreadUser->id));
        $this->assertFalse($unreadIds->contains($readUser->id));
    }

    // TracksStatusHistory

    public function test_updating_status_id_writes_a_status_history_row_with_from_to_and_actor(): void
    {
        $from = $this->correspondenceStatus('Draft');
        $to = $this->correspondenceStatus('Sent');
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $correspondence = Correspondence::factory()->create(['status_id' => $from->id]);
        $correspondence->update(['status_id' => $to->id]);

        $history = $correspondence->statusHistories()->latest('id')->first();

        $this->assertSame('status_id', $history->field);
        $this->assertSame($from->id, $history->from_status_id);
        $this->assertSame($to->id, $history->to_status_id);
        $this->assertSame($actor->id, $history->user_id);
    }
}
