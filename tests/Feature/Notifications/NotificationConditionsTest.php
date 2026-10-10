<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationSetting;
use App\Models\Status;
use App\Models\User;
use App\Notifications\ModelEventNotification;
use App\Services\NotificationValueNormalizer;
use Illuminate\Support\Facades\Notification;
use ReflectionProperty;

class NotificationConditionsTest extends NotificationTestCase
{
    private function watch(array $columns, mixed $values, array $overrides = []): User
    {
        $user = $this->recipient();
        $this->rule($user, ['columns' => $columns, 'values' => $values] + $overrides);

        return $user;
    }

    private function total(User $user): int
    {
        return $this->sentCount($user);
    }

    public function test_a_column_that_becomes_a_listed_value_fires_and_an_unlisted_one_does_not(): void
    {
        $user = $this->watch(['urgency_level'], ['urgency_level' => ['high']]);
        $request = $this->request(['urgency_level' => 'low']);

        $request->update(['urgency_level' => 'medium']);
        $this->assertSame(0, $this->total($user), 'An unlisted value must not fire.');

        $request->update(['urgency_level' => 'high']);
        $this->assertSame(1, $this->total($user), 'Positive control: becoming the listed value fires.');
    }

    public function test_a_typed_value_never_seen_before_matches_exactly(): void
    {
        $user = $this->watch(['notes'], ['notes' => ['brand-new-text']]);
        $request = $this->request(['notes' => 'old']);

        $request->update(['notes' => 'brand-new-text-x']);
        $this->assertSame(0, $this->total($user));

        $request->update(['notes' => 'brand-new-text']);
        $this->assertSame(1, $this->total($user), 'Positive control: the typed value fires.');
    }

    public function test_an_unrelated_watched_column_changing_on_a_record_that_already_holds_the_value_does_not_fire(): void
    {
        $user = $this->watch(['urgency_level', 'notes'], ['urgency_level' => ['high'], 'notes' => ['urgent']]);
        $request = $this->request(['urgency_level' => 'high']);

        $request->update(['notes' => 'calm']);
        $this->assertSame(0, $this->total($user));

        $request->update(['notes' => 'urgent']);
        $this->assertSame(1, $this->total($user), 'Positive control: the other column reaching its own listed value fires.');
    }

    public function test_changing_away_from_a_listed_value_does_not_fire(): void
    {
        $user = $this->watch(['urgency_level'], ['urgency_level' => ['high']]);
        $request = $this->request(['urgency_level' => 'high']);

        $request->update(['urgency_level' => 'low']);
        $this->assertSame(0, $this->total($user));

        $request->update(['urgency_level' => 'high']);
        $this->assertSame(1, $this->total($user), 'Positive control: coming back to the value fires.');
    }

    public function test_moving_between_two_listed_values_fires(): void
    {
        $user = $this->watch(['urgency_level'], ['urgency_level' => ['medium', 'high']]);
        $request = $this->request(['urgency_level' => 'medium']);

        $request->update(['urgency_level' => 'high']);

        $this->assertSame(1, $this->total($user));
    }

    public function test_a_value_listed_for_one_column_never_satisfies_another_column(): void
    {
        $user = $this->watch(['urgency_level', 'notes'], ['urgency_level' => ['high'], 'notes' => ['other']]);
        $request = $this->request(['urgency_level' => 'low']);

        $request->update(['notes' => 'high']);
        $this->assertSame(0, $this->total($user), 'urgency_level\'s value must not satisfy notes.');

        $request->update(['notes' => 'other']);
        $this->assertSame(1, $this->total($user), 'Positive control: the column\'s own value fires.');
    }

    public function test_two_columns_are_matched_independently(): void
    {
        $user = $this->watch(['urgency_level', 'notes'], ['urgency_level' => ['high'], 'notes' => ['a']]);
        $request = $this->request(['urgency_level' => 'low']);

        $request->update(['urgency_level' => 'high']);
        $request->update(['notes' => 'a']);

        $this->assertSame(2, $this->total($user));
    }

    public function test_a_watched_column_without_values_fires_on_any_change(): void
    {
        $user = $this->watch(['urgency_level', 'notes'], ['urgency_level' => ['high']]);
        $request = $this->request(['urgency_level' => 'low']);

        $request->update(['notes' => 'anything']);
        $this->assertSame(1, $this->total($user));

        $request->update(['urgency_level' => 'medium']);
        $this->assertSame(1, $this->total($user), 'Positive control: the valued column still filters.');
    }

    public function test_a_flat_values_list_is_malformed_and_fails_closed_while_a_per_column_map_is_read(): void
    {
        $flat = new NotificationSetting(['settings' => ['columns' => ['a', 'b'], 'values' => [1, 2]]]);
        $map = new NotificationSetting(['settings' => ['columns' => ['a', 'b'], 'values' => ['a' => [1], 'b' => []]]]);

        $this->assertTrue($flat->hasMalformedValues());
        $this->assertSame([], $flat->getColumnValues());
        $this->assertFalse($map->hasMalformedValues());
        $this->assertSame(['a' => [1]], $map->getColumnValues());
    }

    public function test_malformed_stored_values_fail_closed(): void
    {
        $malformed = ['junk', 5, ['urgency_level' => 'high'], ['unknown' => ['high']], [['high']], ['urgency_level' => [['high']]], ['0' => ['high']], [true => 'x']];

        foreach ($malformed as $values) {
            $user = $this->watch(['urgency_level'], $values);
            $this->request(['urgency_level' => 'low'])->update(['urgency_level' => 'high']);

            $this->assertSame(0, $this->total($user), 'Malformed values '.json_encode($values).' must never fire.');
        }

        $user = $this->watch(['urgency_level'], ['urgency_level' => ['high']]);
        $this->request(['urgency_level' => 'low'])->update(['urgency_level' => 'high']);
        $this->assertSame(1, $this->total($user), 'Positive control: a well-formed map fires.');
    }

    public function test_no_columns_means_any_update_fires_and_values_are_ignored(): void
    {
        $user = $this->watch([], 'junk');
        $this->request()->update(['notes' => 'x']);

        $this->assertSame(1, $this->total($user));
    }

    public function test_create_and_delete_match_the_current_value_per_column(): void
    {
        $create = $this->watch(['urgency_level'], ['urgency_level' => ['high']], ['actions' => ['create']]);
        $delete = $this->watch(['urgency_level'], ['urgency_level' => ['high']], ['actions' => ['delete']]);

        $this->request(['urgency_level' => 'low']);
        $this->assertSame(0, $this->total($create));

        $high = $this->request(['urgency_level' => 'high']);
        $this->assertSame(1, $this->total($create), 'Positive control: a created record at the listed value fires.');

        $low = $this->request(['urgency_level' => 'low']);
        $low->delete();
        $this->assertSame(0, $this->total($delete));

        $high->delete();
        $this->assertSame(1, $this->total($delete), 'Positive control: deleting a record at the listed value fires.');
    }

    public function test_create_ignores_a_value_listed_for_a_different_column(): void
    {
        $user = $this->watch(['urgency_level', 'notes'], ['urgency_level' => ['x'], 'notes' => ['high']], ['actions' => ['create']]);

        $this->request(['urgency_level' => 'high', 'notes' => 'plain']);
        $this->assertSame(0, $this->total($user));

        $this->request(['urgency_level' => 'low', 'notes' => 'high']);
        $this->assertSame(1, $this->total($user));
    }

    public function test_decimal_date_datetime_and_id_values_match_across_representations(): void
    {
        $statusId = Status::factory()->create()->id;
        $user = $this->watch(
            ['total_estimated_cost', 'required_by_date', 'approval_date', 'status_id'],
            [
                'total_estimated_cost' => ['150.50'],
                'required_by_date' => ['2026-02-01'],
                'approval_date' => ['2026-03-01 10:00:00'],
                'status_id' => [(string) $statusId],
            ]
        );
        $request = $this->request(['total_estimated_cost' => 1, 'required_by_date' => '2026-01-01', 'approval_date' => null]);

        foreach ([
            ['total_estimated_cost' => 150.5],
            ['required_by_date' => '2026-02-01 00:00:00'],
            ['approval_date' => '2026-03-01 10:00'],
            ['status_id' => $statusId],
        ] as $index => $change) {
            $request->update($change);
            $this->assertSame($index + 1, $this->total($user), 'Normalized match failed for '.key($change));
        }

        $request->update(['total_estimated_cost' => 150.51]);
        $this->assertSame(4, $this->total($user), 'Control: a different decimal does not match.');
    }

    public function test_a_picked_date_fires_on_that_calendar_day_for_a_datetime_column(): void
    {
        $user = $this->watch(['approval_date'], ['approval_date' => ['2026-10-15']]);
        $request = $this->request(['approval_date' => null]);

        $request->update(['approval_date' => '2026-10-16 09:00:00']);
        $this->assertSame(0, $this->total($user), 'Another day must not fire.');

        $request->update(['approval_date' => '2026-10-15 14:30:00']);
        $this->assertSame(1, $this->total($user), 'Positive control: any time on the picked day fires.');

        $exact = $this->watch(['approval_date'], ['approval_date' => ['2026-10-20 10:00:00']]);
        $request->update(['approval_date' => '2026-10-20 11:00:00']);
        $this->assertSame(0, $this->total($exact), 'A full datetime stays an exact match.');
    }

    public function test_a_picked_date_fires_only_when_the_record_moves_into_that_day(): void
    {
        $user = $this->watch(['approval_date'], ['approval_date' => ['2026-10-15']]);
        $created = $this->watch(['approval_date'], ['approval_date' => ['2026-10-15']], ['actions' => ['create']]);
        $request = $this->request(['approval_date' => '2026-10-15 09:00:00']);
        $this->assertSame(1, $this->total($created), 'Create on the picked day fires.');

        $request->update(['approval_date' => '2026-10-15 18:45:00']);
        $this->assertSame(0, $this->total($user), 'A time-only change inside the picked day must not fire.');

        $request->update(['approval_date' => '2026-10-15 00:00:00']);
        $this->assertSame(0, $this->total($user), 'Moving to midnight of the same day is still inside the picked day.');

        $request->update(['approval_date' => '2026-10-16 08:00:00']);
        $request->update(['approval_date' => '2026-10-15 08:00:00']);
        $this->assertSame(1, $this->total($user), 'Moving from another day into the picked day fires.');
    }

    public function test_numbers_and_boolean_flags_compare_numerically(): void
    {
        $user = $this->watch(['total_estimated_cost'], ['total_estimated_cost' => ['5.0']]);
        $this->request(['total_estimated_cost' => 1])->update(['total_estimated_cost' => 5]);
        $this->assertSame(1, $this->total($user), "'5.0' matches 5.");

        $n = fn ($v) => NotificationValueNormalizer::normalize($v, 'correspondences', 'is_internal');
        $this->assertSame('1', $n(true));
        $this->assertSame('1', $n('1'));
        $this->assertSame('1', $n(1));
        $this->assertSame('0', $n(false));
    }

    public function test_normalizer_collapses_equivalent_representations_and_rejects_non_scalars(): void
    {
        $n = fn ($v, $c) => NotificationValueNormalizer::normalize($v, 'purchase_requests', $c);

        $this->assertSame($n('10.50000', 'total_estimated_cost'), $n(10.5, 'total_estimated_cost'));
        $this->assertSame('0', $n('-0.000', 'total_estimated_cost'));
        $this->assertSame('5', $n('5', 'status_id'));
        $this->assertSame($n(5, 'status_id'), $n('5', 'status_id'));
        $this->assertSame('2026-02-01', $n(new \DateTime('2026-02-01 13:00'), 'required_by_date'));
        $this->assertSame('1', $n(true, 'urgency_level'));
        $this->assertNull($n(['x'], 'urgency_level'));
        $this->assertNull($n(null, 'urgency_level'));
        $this->assertNull($n('not-a-number', 'total_estimated_cost'));
        $this->assertNull($n('garbage', 'required_by_date'));
    }

    public function test_update_messages_list_only_watched_changed_columns_and_never_system_columns(): void
    {
        $watched = $this->recipient();
        $this->rule($watched, ['columns' => ['urgency_level']]);
        $all = $this->recipient();
        $this->rule($all);

        $this->request(['urgency_level' => 'low', 'notes' => null])->update(['urgency_level' => 'high', 'notes' => 'changed']);

        $this->assertSame(['urgency_level'], array_keys($this->changesSentTo($watched)));
        $this->assertSame(['urgency_level', 'notes'], array_keys($this->changesSentTo($all)), 'Positive control: without watched columns every changed non-system column is listed.');
    }

    private function changesSentTo(User $user): array
    {
        $property = new ReflectionProperty(ModelEventNotification::class, 'changes');

        return $property->getValue(Notification::sent($user, ModelEventNotification::class)->first());
    }
}
