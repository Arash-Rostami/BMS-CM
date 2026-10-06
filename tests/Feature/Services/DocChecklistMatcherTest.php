<?php

namespace Tests\Feature\Services;

use App\Models\Shipment;
use App\Models\User;
use App\Services\DocChecklistMatcher;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocChecklistMatcherTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
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

    private function shipment(array $rows): Shipment
    {
        return Shipment::factory()->create(['docs' => $rows]);
    }

    private function attach(Shipment $shipment, string $filename): void
    {
        $shipment->attachments()->create([
            'name' => $filename,
            'path' => 'attachments/'.uniqid(),
            'type' => 'application/pdf',
            'user_id' => $this->user->id,
        ]);
    }

    private function row(Shipment $shipment, string $name): array
    {
        return collect($shipment->fresh()->documentChecklist())->firstWhere('name', $name);
    }

    public function test_sync_ticks_a_row_whose_label_matches_an_attachment_filename(): void
    {
        $shipment = $this->shipment([
            ['name' => 'ci', 'received' => false],
            ['name' => 'insurance', 'received' => false],
            ['name' => 'track', 'received' => true],
        ]);
        $this->attach($shipment, 'Commercial Invoice (CI).pdf');

        DocChecklistMatcher::sync($shipment);

        $this->assertTrue($this->row($shipment, 'ci')['received']);
        $this->assertFalse($this->row($shipment, 'insurance')['received']);
        $this->assertTrue($this->row($shipment, 'track')['received']);
    }

    public function test_sync_requires_short_codes_to_match_a_whole_token(): void
    {
        $matching = $this->shipment([['name' => 'do', 'received' => false]]);
        $this->attach($matching, 'DO signed.pdf');

        $nonMatching = $this->shipment([['name' => 'do', 'received' => false]]);
        $this->attach($nonMatching, 'shipment documents.pdf');

        DocChecklistMatcher::sync($matching);
        DocChecklistMatcher::sync($nonMatching);

        $this->assertTrue($this->row($matching, 'do')['received']);
        $this->assertFalse($this->row($nonMatching, 'do')['received']);
    }

    public function test_sync_unticks_a_received_row_when_the_attachment_is_gone(): void
    {
        $shipment = $this->shipment([['name' => 'ci', 'received' => true]]);

        DocChecklistMatcher::sync($shipment);

        $this->assertFalse($this->row($shipment, 'ci')['received']);
    }

    public function test_sync_noops_when_document_tracking_is_disabled(): void
    {
        $shipment = $this->shipment([
            ['name' => 'track', 'received' => false],
            ['name' => 'ci', 'received' => true],
        ]);

        DocChecklistMatcher::sync($shipment);

        $this->assertTrue($this->row($shipment, 'ci')['received']);
    }

    public function test_sync_folds_arabic_glyph_variants_when_matching_farsi_labels(): void
    {
        $shipment = $this->shipment([['name' => 'ci', 'received' => false]]);
        $this->attach($shipment, 'فاكتور تجاری (CI).pdf');

        DocChecklistMatcher::sync($shipment);

        $this->assertTrue($this->row($shipment, 'ci')['received']);
    }

    public function test_sync_noops_when_the_checklist_has_no_rows(): void
    {
        $shipment = $this->shipment([]);

        DocChecklistMatcher::sync($shipment);

        $this->assertSame([], $shipment->fresh()->documentChecklist());
    }
}