<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendar_reads')) {
            Schema::create('calendar_reads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('calendar_hit_id')->constrained('calendar_hits')->cascadeOnDelete();
                $table->date('event_date')->comment('Hit date when hidden; a moved date reopens the item');
                $table->timestamp('read_at')->comment('When the user hid the item');

                $table->unique(['user_id', 'calendar_hit_id'], 'calendar_reads_user_hit_unique');
                $table->index('calendar_hit_id', 'idx_calendar_reads_hit');
            });
        }

        DB::transaction(fn () => $this->copyLegacyMarks());
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_reads');
    }

    private function copyLegacyMarks(): void
    {
        DB::table('users')
            ->whereRaw("JSON_CONTAINS_PATH(settings, 'one', '$.calendar_seen')")
            ->select('id', 'settings')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $settings = json_decode($user->settings, true) ?: [];
                    $marks = (array) ($settings['calendar_seen'] ?? []);

                    $this->insertMarks($user->id, $marks);

                    unset($settings['calendar_seen']);
                    DB::table('users')->where('id', $user->id)->update(['settings' => $settings === [] ? null : json_encode($settings)]);
                }
            });
    }

    private function insertMarks(int $userId, array $marks): void
    {
        $existing = DB::table('calendar_hits')->whereIn('id', array_keys($marks))->pluck('id')->all();

        $rows = collect($existing)->map(function ($hitId) use ($userId, $marks): ?array {
            $mark = is_array($marks[$hitId]) ? $marks[$hitId] : [];
            $seenAt = $mark[0] ?? $mark['seen_at'] ?? null;
            $date = $mark[1] ?? $mark['event_date'] ?? null;

            try {
                return [
                    'user_id' => $userId,
                    'calendar_hit_id' => $hitId,
                    'event_date' => Carbon::parse($date)->toDateString(),
                    'read_at' => (is_numeric($seenAt) ? Carbon::createFromTimestamp($seenAt, config('app.timezone')) : Carbon::parse($seenAt))->toDateTimeString(),
                ];
            } catch (\Throwable) {
                return null;
            }
        })->filter()->values()->all();

        DB::table('calendar_reads')->insertOrIgnore($rows);
    }
};
