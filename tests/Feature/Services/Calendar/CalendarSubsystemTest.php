<?php

namespace Tests\Feature\Services\Calendar;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\CalendarColor;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility;
use App\Jobs\SendCalendarRuleAlerts;
use App\Jobs\SyncCalendarRule;
use App\Jobs\SyncCalendarSubject;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Correspondence;
use App\Models\Department;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\CalendarAlertNotification;
use App\Services\Calendar\CalendarActivity;
use App\Services\Calendar\CalendarAlerts;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\CalendarPathResolver;
use App\Services\Calendar\Display\CalendarBoard;
use App\Services\Calendar\Display\CalendarPresenter;
use App\Services\Calendar\Display\CalendarRange;
use App\Services\Calendar\Sync\CalendarEngine;
use App\Services\Calendar\Sync\CalendarFilterTree;
use App\Services\Calendar\Sync\CalendarRouter;
use App\Services\NameSearch;
use App\Services\PredefinedOptions;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Filament\QueryBuilder\Constraints\RelationshipConstraint;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Morilog\Jalali\Jalalian;
use RuntimeException;
use Tests\TestCase;

class CalendarSubsystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        DB::table('calendar_hits')->delete();
        DB::table('calendar_rules')->delete();
        app()->setLocale('en');
        Carbon::setTestNow(Carbon::parse('2026-10-08'));
        $this->forgetResolverCaches();
        CalendarHit::flushVisibleRuleIds();
        CalendarRouter::flushRoutes();
        Queue::fake(); // observers are live: never let a dispatch run inline under QUEUE_CONNECTION=sync
    }

    protected function tearDown(): void
    {
        $this->forgetResolverCaches();
        CalendarHit::flushVisibleRuleIds();
        CalendarRouter::flushRoutes();
        Carbon::setTestNow();
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

    private function forgetResolverCaches(): void
    {
        $classes = [
            'App\Models\BankProfile', 'App\Models\Category', 'App\Models\Company', 'App\Models\Custom',
            'App\Models\Payment', 'App\Models\Product', 'App\Models\ProformaInvoice', 'App\Models\PurchaseOrder',
            'App\Models\PurchaseRequest', 'App\Models\PurchaseRequestItem', 'App\Models\RegisteredOrder',
            'App\Models\Shipment', 'App\Models\User', 'App\Models\CalendarHit', 'App\Models\CalendarRule',
            'App\Models\Status', 'App\Models\Department', 'App\Models\Bank', 'App\Models\Currency',
            'App\Models\Attachment', 'App\Models\EntityAttribute', 'App\Models\Role', 'App\Models\Permission',
        ];

        foreach ($classes as $class) {
            Cache::forget('calendar_columns:'.(new $class)->getTable());
            Cache::forget('calendar_relations:'.$class);
        }
    }

    private function resolver(): CalendarPathResolver
    {
        return new CalendarPathResolver(new CalendarFilterTree);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);

        return $user;
    }

    // CalendarModules

    public function test_all_maps_the_workspace_modules(): void
    {
        $all = CalendarModules::all();

        $this->assertCount(9, $all);
        $this->assertSame(PurchaseRequest::class, array_key_first($all));
        $this->assertSame('pr_number', $all[PurchaseRequest::class]['identifier']);
        $this->assertSame('filament.dashboard.resources.purchase-requests.edit', $all[PurchaseRequest::class]['route']);
        $this->assertNotSame('', $all[PurchaseRequest::class]['label']);
        $this->assertSame(Correspondence::class, array_key_last($all));
    }

    public function test_the_subject_eager_load_map_covers_every_module_with_a_status_relation(): void
    {
        $withStatus = collect(config('workspace.resources'))
            ->filter(fn (array $config): bool => isset($config['status']))
            ->map(fn (array $config): string => $config['model'])
            ->sort()
            ->values()
            ->all();

        $this->assertSame($withStatus, collect(CalendarBoard::MORPH_WITH)->keys()->sort()->values()->all());
    }

    public function test_viewable_by_grants_only_permitted_modules(): void
    {
        $user = $this->userWithPermissions(['purchase_request.view']);

        $this->assertSame([PurchaseRequest::class], CalendarModules::viewableBy($user));
        $this->assertSame([], CalendarModules::viewableBy($this->userWithPermissions([])));
    }

    public function test_item_label_uses_identifier_with_id_fallback(): void
    {
        $request = PurchaseRequest::factory()->create();
        $hit = CalendarHit::factory()->create();

        $this->assertSame((string) $request->pr_number, CalendarModules::itemLabel($request));
        $this->assertSame('#'.$hit->getKey(), CalendarModules::itemLabel($hit));
    }

    public function test_url_builds_the_module_edit_route(): void
    {
        $request = PurchaseRequest::factory()->create();

        $this->assertStringContainsString('/purchase-requests/'.$request->getKey(), CalendarModules::url($request));
    }

    // CalendarPathResolver — columns and relations

    public function test_columns_typed_by_schema_and_blocklist_applied(): void
    {
        $columns = $this->resolver()->columns(User::class);

        $this->assertSame('number', $columns['id']);
        $this->assertSame('text', $columns['name']);
        $this->assertSame('date', $columns['created_at']);
        $this->assertArrayNotHasKey('password', $columns);
        $this->assertArrayNotHasKey('remember_token', $columns);
    }

    public function test_relations_expose_typed_pivot_links_and_exclude_morph_to_and_vendor_methods(): void
    {
        $requests = $this->resolver()->relations(PurchaseRequest::class);

        $this->assertArrayHasKey('registeredOrders', $requests);
        $this->assertFalse($requests['registeredOrders']['single']);
        $this->assertArrayHasKey('requester', $requests);
        $this->assertTrue($requests['requester']['single']);
        $this->assertFalse($requests['items']['single']);
        $this->assertArrayNotHasKey('subject', $this->resolver()->relations(CalendarHit::class));
        $this->assertArrayNotHasKey('roles', $this->resolver()->relations(User::class));
    }

    // CalendarPathResolver — validatePath

    public function test_validate_path_accepts_column_and_nested_paths(): void
    {
        $resolver = $this->resolver();

        $this->assertSame([], $resolver->validatePath(PurchaseRequest::class, 'pr_number'));

        $hops = $resolver->validatePath(PurchaseRequest::class, 'items.product.name');

        $this->assertSame([
            ['relation' => 'items', 'class' => PurchaseRequestItem::class],
            ['relation' => 'product', 'class' => Product::class],
        ], $hops);
    }

    public function test_validate_path_rejects_undeclared_hops_cycles_and_too_deep_paths(): void
    {
        $resolver = $this->resolver();
        $original = config('calendar.max_path_depth');

        try {
            foreach (['typo.name', 'items.purchaseRequest.pr_number'] as $path) {
                try {
                    $resolver->validatePath(PurchaseRequest::class, $path);
                    $this->fail("Expected ValidationException for [{$path}].");
                } catch (ValidationException) {
                    $this->addToAssertionCount(1);
                }
            }

            config(['calendar.max_path_depth' => 1]);

            $this->expectException(ValidationException::class);
            $resolver->validatePath(PurchaseRequest::class, 'items.product.name');
        } finally {
            config(['calendar.max_path_depth' => $original]);
        }
    }

    // CalendarPathResolver — constraints

    public function test_constraints_for_type_columns_and_swap_foreign_keys_for_relationships(): void
    {
        $byName = collect($this->resolver()->constraintsFor(PurchaseRequest::class))
            ->keyBy(fn (Constraint $constraint): string => $constraint->getName());

        $this->assertInstanceOf(DateConstraint::class, $byName['required_by_date']);
        $this->assertInstanceOf(TextConstraint::class, $byName['pr_number']);
        $this->assertInstanceOf(RelationshipConstraint::class, $byName['requester']);
        $this->assertArrayNotHasKey('requester_id', $byName);
        $this->assertArrayNotHasKey('requester.name', $byName);
        $this->assertNotContains(true, $byName->keys()->map(fn (string $name): bool => str_contains($name, '.'))->all());
    }

    public function test_relationship_selector_searches_every_name_column_and_labels_by_locale(): void
    {
        $department = Department::factory()->create(['name' => 'بخش مالی پژوهشی', 'english_name' => 'Probe Finance', 'code' => 'PRB-77']);
        $constraint = collect($this->resolver()->constraintsFor(PurchaseRequest::class))
            ->first(fn (Constraint $constraint): bool => $constraint->getName() === 'department');
        $search = fn (string $term): array => $constraint->getOperator('isRelatedTo')->constraint($constraint)->getFormSchema()[0]->getSearchResults($term);

        foreach (['Probe Fin', 'مالی پژو', 'PRB-77'] as $term) {
            $this->assertArrayHasKey($department->id, $search($term), $term);
        }

        app()->setLocale('en');
        $this->assertSame('Probe Finance — بخش مالی پژوهشی', $search('مالی پژو')[$department->id]);
        app()->setLocale('fa');
        $this->assertSame('بخش مالی پژوهشی — Probe Finance', $search('Probe Fin')[$department->id]);
        $this->assertSame([], $search("Probe%' OR 1=1 --_\\"));

        $rule = CalendarRule::factory()->make(['subject' => PurchaseRequest::class, 'filters' => ['rules' => [
            ['type' => 'department', 'data' => ['operator' => 'isRelatedTo', 'settings' => ['value' => $department->id]]],
        ]]]);
        $this->assertStringContainsString('بخش مالی پژوهشی', $this->resolver()->summaries($rule)[0]);
        app()->setLocale('en');
        $this->assertStringContainsString('Probe Finance', $this->resolver()->summaries($rule)[0]);
    }

    public function test_a_single_name_column_model_keeps_its_one_column_selector(): void
    {
        $this->assertSame(['name'], NameSearch::columns(new Role));
        $this->assertSame('name', NameSearch::display(new Role));
        $role = Role::query()->first() ?? Role::create(['name' => 'probe_solo_role', 'guard_name' => 'web']);
        $this->assertArrayHasKey($role->id, NameSearch::options(new Role, substr($role->name, 0, 5)));
    }

    public function test_constraints_for_adds_only_the_chosen_related_records_with_clean_labels(): void
    {
        app()->setLocale('en');
        $byName = collect($this->resolver()->constraintsFor(PurchaseRequest::class, ['requester']))
            ->keyBy(fn (Constraint $constraint): string => $constraint->getName());

        $this->assertInstanceOf(TextConstraint::class, $byName['requester.name']);
        $this->assertSame('Requester › Name', $byName['requester.name']->getLabel());
        $this->assertArrayNotHasKey('creator.name', $byName);
        $this->assertArrayNotHasKey('requester.requester_id', $byName);
    }

    public function test_constraints_for_rule_derives_relations_from_legacy_filters_and_extra_paths(): void
    {
        $rule = CalendarRule::factory()->make([
            'subject' => PurchaseRequest::class,
            'filters' => ['rules' => [
                'a' => ['type' => 'pr_number', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'X']]],
                'b' => ['type' => 'items.product.name', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'X']]],
            ]],
            'extra_paths' => [],
        ]);

        $names = collect($this->resolver()->constraintsForRule($rule))
            ->map(fn (Constraint $constraint): string => $constraint->getName())
            ->all();

        $this->assertEqualsCanonicalizing(['pr_number', 'items.product.name'], $names);
        $this->assertSame(['items.product'], $this->resolver()->relationPathsFor(PurchaseRequest::class, ['items.product.name'], $rule->filters['rules']));
        $this->assertSame(['requester'], $this->resolver()->relationPathsFor(PurchaseRequest::class, ['requester', 'bogus.path', 7]));
    }

    public function test_table_options_put_the_module_first_then_direct_links_then_two_hop_tables_without_duplicates(): void
    {
        app()->setLocale('en');
        $options = $this->resolver()->tableOptions(PurchaseRequest::class);
        $all = array_merge($options['direct'], $options['far']);

        $this->assertSame(CalendarPathResolver::OWN_TABLE, array_key_first($options['direct']));
        $this->assertSame(CalendarModules::label(PurchaseRequest::class), $options['direct'][CalendarPathResolver::OWN_TABLE]);
        $this->assertSame(count($all), count(array_unique($all)));
        $this->assertNotEmpty($options['far']);

        foreach (array_keys($options['direct']) as $path) {
            $this->assertSame(0, substr_count($path, '.'), $path);
        }

        foreach ($options['far'] as $path => $label) {
            $this->assertSame(1, substr_count($path, '.'), $path);
            $this->assertStringContainsString(' (', $label);
            $this->assertStringNotContainsString(' › ', $label);
        }
    }

    public function test_table_options_reach_a_table_through_a_direct_link_but_never_a_third_hop(): void
    {
        app()->setLocale('en');
        $options = $this->resolver()->tableOptions(PurchaseRequest::class);
        $viaOrder = CalendarModules::label(Shipment::class).' (via '.CalendarModules::label(RegisteredOrder::class).')';

        $this->assertContains($viaOrder, $options['far']);
        $this->assertSame([], array_filter(array_keys($options['far']), fn (string $path): bool => substr_count($path, '.') > 1));
        $this->assertSame([], array_filter(array_keys($this->resolver()->tableOptions(Shipment::class)['far']), fn (string $path): bool => substr_count($path, '.') > 1));
    }

    public function test_table_options_hide_user_noise_technical_links_and_unviewable_modules(): void
    {
        app()->setLocale('en');
        $options = $this->resolver()->tableOptions(PurchaseRequest::class);

        foreach (array_keys(array_merge($options['direct'], $options['far'])) as $path) {
            if ($path === CalendarPathResolver::OWN_TABLE) {
                continue;
            }

            foreach ($this->resolver()->validateRelationPath(PurchaseRequest::class, $path) as $hop) {
                $this->assertNotSame(User::class, $hop['class'], $path);
            }

            $this->assertDoesNotMatchRegularExpression('/attachments|customAttributes|extraAttributes|statusHistories|Exclusive|creator|updater/i', $path);
        }

        $none = $this->resolver()->tableOptions(PurchaseRequest::class, $this->userWithPermissions([]));
        $this->assertSame([CalendarPathResolver::OWN_TABLE], array_keys($none['direct']));
        $this->assertSame([], $none['far']);

        $first = array_key_first(array_diff_key($options['direct'], [CalendarPathResolver::OWN_TABLE => 1]));
        $class = $this->resolver()->relations(PurchaseRequest::class)[$first]['related'];
        $some = $this->resolver()->tableOptions(PurchaseRequest::class, $this->userWithPermissions([Str::snake(class_basename($class)).'.view']));
        $this->assertArrayHasKey($first, $some['direct']);
        $this->assertCount(1 + count(array_filter($this->resolver()->relations(PurchaseRequest::class), fn (array $meta, string $relation): bool => $meta['related'] === $class && ! in_array($relation, ['creator', 'updater'], true) && ! str_ends_with($relation, 'Exclusive'), ARRAY_FILTER_USE_BOTH)), $some['direct']);
    }

    public function test_used_paths_come_from_dotted_filter_leaves_only_and_survive_hostile_input(): void
    {
        $leaf = fn (string $type): array => ['type' => $type, 'data' => ['operator' => 'equals', 'settings' => ['text' => 'X']]];

        $this->assertSame(['creator'], $this->resolver()->usedPaths(PurchaseRequest::class, [$leaf('creator.name'), $leaf('pr_number'), $leaf('requester')]));
        $this->assertSame([], $this->resolver()->usedPaths(PurchaseRequest::class, 'nope'));
        $this->assertSame([], $this->resolver()->usedPaths(PurchaseRequest::class, [['type' => 'or', 'data' => 'x']]));
        $this->assertSame([], $this->resolver()->usedPaths(PurchaseRequest::class, null));
    }

    public function test_condition_labels_lead_with_the_module_name_and_reuse_the_field_strings(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $prefix = CalendarModules::label(PurchaseRequest::class).' › ';
            $labels = array_map(fn (Constraint $constraint): string => (string) $constraint->getLabel(), $this->resolver()->constraintsFor(PurchaseRequest::class));

            $this->assertNotEmpty($labels);

            foreach ($labels as $label) {
                $this->assertStringStartsWith($prefix, $label, $locale);
                $this->assertDoesNotMatchRegularExpression('/_|[a-z][A-Z]/', substr($label, strlen($prefix)), $label);
            }

            $own = trans('resources/purchaseRequest/strings.form.pr_number', [], $locale);
            $this->assertContains($prefix.trim(Str::before($own, ' — ')), $labels, $locale);
        }
    }

    public function test_technical_columns_stay_hidden_for_a_user_unless_a_saved_condition_uses_them(): void
    {
        $user = $this->userWithPermissions(['purchase_request.view', 'user.view', 'department.view', 'status.view']);
        $names = fn (array $keep = []): array => array_map(fn (Constraint $constraint): string => $constraint->getName(), $this->resolver()->constraintsFor(PurchaseRequest::class, [], $user, $keep));

        $this->assertNotContains('id', $names());
        $this->assertContains('id', $names(['id']));
        $this->assertContains('id', array_map(fn (Constraint $constraint): string => $constraint->getName(), $this->resolver()->constraintsFor(PurchaseRequest::class)));
    }

    public function test_the_resolver_is_a_shared_singleton(): void
    {
        $this->assertSame(app(CalendarPathResolver::class), app(CalendarPathResolver::class));
    }

    public function test_the_sentence_restates_conditions_in_the_active_locale(): void
    {
        $leaf = ['type' => 'pr_number', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'X']]];

        foreach (['en' => 'and', 'fa' => 'و', 'fr' => 'et'] as $locale => $and) {
            app()->setLocale($locale);
            $sentence = $this->resolver()->sentence(PurchaseRequest::class, [], [$leaf, $leaf]);

            $this->assertStringContainsString(" {$and} ", $sentence);
        }

        $this->assertSame('', $this->resolver()->sentence(PurchaseRequest::class, [], []));
    }

    public function test_every_calendar_label_is_translated_in_fa_and_fr(): void
    {
        $user = $this->userWithPermissions(collect(CalendarModules::all())->keys()->flatMap(fn (string $class): array => [Str::snake(class_basename($class)).'.view'])->merge(['user.view', 'status.view', 'company.view', 'bank.view', 'currency.view', 'department.view', 'correspondence.view'])->all());
        $allowed = ['ETA', 'ETD', 'IBAN', 'BL', 'ID', 'HS', 'Notes', 'Description', 'Types', 'Type', 'Total', 'Date', 'Incoterms'];

        $untranslated = [];

        foreach (['fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $resolver = $this->resolver();

            foreach (array_keys(CalendarModules::all()) as $subject) {
                $tables = $resolver->tableOptions($subject, $user);
                $links = array_keys(array_diff_key(array_merge($tables['direct'], $tables['far']), [CalendarPathResolver::OWN_TABLE => 1]));
                $labels = [
                    ...array_values($tables['direct']),
                    ...array_values($tables['far']),
                    ...array_map(fn (Constraint $constraint): string => (string) $constraint->getLabel(), $resolver->constraintsFor($subject, $links, $user)),
                    ...array_values($resolver->datePathOptions($subject, $user)),
                ];
                $raw = collect([$subject, ...array_map(fn (string $link): string => Arr::last($resolver->validateRelationPath($subject, $link))['class'], $links)])
                    ->flatMap(fn (string $class): array => [Str::headline(class_basename($class)), ...array_keys($resolver->columns($class)), ...array_keys($resolver->relations($class))])
                    ->map(fn (string $name): string => Str::headline(Str::replaceEnd('_id', '', Str::snake($name))))
                    ->diff($allowed)->unique()->all();

                foreach ($labels as $label) {
                    foreach (explode(' › ', $label) as $segment) {
                        in_array($segment, $raw, true) && $untranslated[$locale.' '.$segment] = true;
                    }
                }
            }
        }

        $this->assertSame([], array_keys($untranslated));
    }

    // CalendarPathResolver — date paths, related models, suggestions

    public function test_date_path_options_cover_direct_and_single_valued_paths_only(): void
    {
        $options = $this->resolver()->datePathOptions(PurchaseRequest::class);

        $this->assertArrayHasKey('required_by_date', $options);
        $this->assertArrayNotHasKey('approver.last_log_in', $options);
        $this->assertArrayHasKey('registeredOrder.expected_delivery_date', $this->resolver()->datePathOptions(Shipment::class));
        $this->assertArrayNotHasKey('creator.created_at', $options);
        $this->assertArrayNotHasKey('items.product.created_at', $options);
        $this->assertTrue(collect($options)->keys()->every(fn (string $path): bool => ! str_starts_with($path, 'items.')));
    }

    public function test_grouped_date_path_options_group_by_module_hide_system_columns_and_carry_the_hint(): void
    {
        app()->setLocale('en');
        $grouped = $this->resolver()->groupedDatePathOptions(PurchaseRequest::class);
        $flat = collect($grouped)->collapse();

        $this->assertSame(CalendarModules::label(PurchaseRequest::class), array_key_first($grouped));
        $this->assertStringContainsString('when the goods are needed', $grouped[CalendarModules::label(PurchaseRequest::class)]['required_by_date']);
        $this->assertArrayHasKey('registeredOrder.expected_delivery_date', $this->resolver()->groupedDatePathOptions(Shipment::class)[CalendarModules::label(RegisteredOrder::class)]);
        $this->assertEqualsCanonicalizing(array_keys($this->resolver()->datePathOptions(PurchaseRequest::class)), $flat->keys()->all());

        $this->assertArrayHasKey('created_at', $flat);
        $this->assertArrayHasKey('updated_at', $flat);
        foreach (['created_at', 'updated_at', 'deleted_at'] as $system) {
            $this->assertTrue($flat->keys()->every(fn (string $path): bool => $path === $system ? $system !== 'deleted_at' : ! str_ends_with($path, $system)));
        }
    }

    public function test_describe_path_names_the_meaning_and_module_and_rejects_invalid_paths(): void
    {
        app()->setLocale('en');
        $info = $this->resolver()->describePath(PurchaseRequest::class, 'required_by_date');

        $this->assertStringContainsString('when the goods are needed', $info['label']);
        $this->assertSame(CalendarModules::label(PurchaseRequest::class), $info['module']);
        $this->assertNull($this->resolver()->describePath(PurchaseRequest::class, 'nonexistent.nothing'));
    }

    public function test_date_hints_and_helper_texts_exist_in_every_locale(): void
    {
        $columns = collect(trans('resources/calendarRule/strings.date_hints', [], 'en'))
            ->flatMap(fn (array $hints, string $table): array => collect($hints)->keys()->map(fn (string $column): string => "{$table}.{$column}")->all());

        $this->assertCount(27, $columns);

        foreach (['en', 'fa', 'fr'] as $locale) {
            foreach ($columns as $dotted) {
                $key = "resources/calendarRule/strings.date_hints.{$dotted}";
                $this->assertTrue(Lang::has($key, $locale), "{$locale}: {$key}");
                $this->assertNotSame($key, trans($key, [], $locale));
            }

            foreach (['date_path_hint', 'date_path_chosen', 'table', 'tables_direct', 'tables_far'] as $name) {
                $this->assertTrue(Lang::has("resources/calendarRule/strings.form.{$name}", $locale), "{$locale}: {$name}");
            }
        }

        $this->assertStringNotContainsString('نویسه', json_encode(trans('resources/calendarRule/strings.date_hints', [], 'fa'), JSON_UNESCAPED_UNICODE));
    }

    public function test_related_models_walks_filter_paths_and_the_date_path(): void
    {
        $rule = CalendarRule::factory()->make([
            'subject' => PurchaseRequest::class,
            'filters' => ['rules' => [
                'a' => ['type' => 'items.product.name', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'X']]],
            ]],
            'date_path' => 'required_by_date',
        ]);

        $related = $this->resolver()->relatedModels($rule);

        $this->assertSame([PurchaseRequestItem::class, Product::class], $related);
    }

    // CalendarPathResolver — summaries

    public function test_summaries_render_leaves_and_or_blocks(): void
    {
        $leaf = fn (string $text): array => ['type' => 'pr_number', 'data' => ['operator' => 'equals', 'settings' => ['text' => $text]]];
        $rule = CalendarRule::factory()->make([
            'subject' => PurchaseRequest::class,
            'filters' => ['rules' => [
                $leaf('X'),
                ['type' => 'or', 'data' => ['groups' => [
                    ['rules' => [$leaf('Y'), $leaf('Z')]],
                    ['rules' => [$leaf('W')]],
                ]]],
            ]],
        ]);

        $lines = $this->resolver()->summaries($rule);

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('equals X', $lines[0]);
        $this->assertMatchesRegularExpression('/^\(.+ equals Y and .+ equals Z\) or \(.+ equals W\)$/', $lines[1]);
    }

    public function test_summaries_tolerate_a_hostile_rules_node(): void
    {
        $shapes = [
            ['rules' => 'nope'],
            ['rules' => [['type' => 'x', 'data' => 'nope'], 'zz']],
            ['rules' => [['type' => 'or', 'data' => 'nope']]],
            ['rules' => [['type' => 'or', 'data' => ['groups' => 'nope']]]],
            ['rules' => [['type' => 'or', 'data' => ['groups' => [['rules' => 'nope'], 'zz']]]]],
            '"nope"',
        ];

        foreach ($shapes as $filters) {
            $rule = CalendarRule::factory()->make(['subject' => PurchaseRequest::class, 'filters' => $filters]);

            $this->assertSame([], $this->resolver()->summaries($rule));
        }
    }

    // CalendarRange

    public function test_gregorian_month_covers_the_calendar_month_with_monday_first_offset(): void
    {
        $range = CalendarRange::forMonth('2026-10-08', false);

        $this->assertSame('2026-10-01', $range->start->toDateString());
        $this->assertSame('2026-10-31', $range->end->toDateString());
        $this->assertCount(31, $range->days());
        $this->assertSame(3, $range->weekdayOffset());
        $this->assertSame('October 2026', $range->label());
    }

    public function test_jalali_month_covers_the_jalali_month_with_saturday_first_offset(): void
    {
        $range = CalendarRange::forMonth('2026-10-08', true);

        $this->assertSame('2026-09-23', $range->start->toDateString());
        $this->assertSame('2026-10-22', $range->end->toDateString());
        $this->assertCount(30, $range->days());
        $this->assertSame(4, $range->weekdayOffset());
        $this->assertSame('مهر 1405', $range->label());
    }

    public function test_jalali_year_boundaries(): void
    {
        $farvardin = CalendarRange::forMonth('2026-03-21', true);
        $esfand = CalendarRange::forMonth('2026-03-20', true);

        $this->assertSame('2026-03-21', $farvardin->start->toDateString());
        $this->assertSame('2026-04-20', $farvardin->end->toDateString());
        $this->assertCount(31, $farvardin->days());
        $this->assertSame('2026-03-20', $esfand->end->toDateString());
    }

    public function test_every_pipeline_link_is_exposed_and_callable_on_all_eight_modules(): void
    {
        $expected = [
            \App\Models\RegisteredOrder::class => ['proformaInvoices', 'purchaseOrders', 'purchaseRequests', 'customs'],
            \App\Models\Shipment::class => ['customs'],
            PurchaseRequest::class => ['registeredOrders', 'proformaInvoices', 'purchaseOrders'],
            \App\Models\ProformaInvoice::class => ['registeredOrders', 'purchaseOrders', 'purchaseRequests'],
            \App\Models\PurchaseOrder::class => ['registeredOrders', 'proformaInvoices', 'purchaseRequests'],
        ];

        foreach ($expected as $class => $links) {
            $relations = $this->resolver()->relations($class);

            foreach ($links as $link) {
                $this->assertArrayHasKey($link, $relations, class_basename($class).'::'.$link);
                $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\Relation::class, (new $class)->{$link}());
            }
        }
    }

    public function test_indirect_relations_never_reach_the_calendar_relations_or_table_options(): void
    {
        $indirect = [
            PurchaseRequest::class => ['purchaseOrderPayments', 'shipments', 'customs'],
            Shipment::class => ['purchaseRequests'],
            \App\Models\Custom::class => ['purchaseRequests'],
            \App\Models\Product::class => ['purchaseRequests'],
        ];

        foreach ($indirect as $class => $names) {
            foreach ($names as $name) {
                $this->assertNotEmpty((new \ReflectionMethod($class, $name))->getAttributes(\App\Models\Attributes\Indirect::class), class_basename($class).'::'.$name);
                $this->assertArrayNotHasKey($name, $this->resolver()->relations($class), class_basename($class).'::'.$name);
            }
        }

        foreach (array_keys(CalendarModules::all()) as $subject) {
            $options = $this->resolver()->tableOptions($subject);
            $paths = array_keys(array_merge($options['direct'], $options['far']));

            foreach ($this->resolver()->relations($subject) as $name => $meta) {
                $this->assertNotInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class, (new $subject)->{$name}(), class_basename($subject).'::'.$name);
            }

            foreach ($indirect[$subject] ?? [] as $name) {
                $this->assertNotContains($name, $paths, class_basename($subject).'::'.$name);
                $this->assertFalse($this->resolver()->isRelationPath($subject, $name), class_basename($subject).'::'.$name);
            }
        }
    }

    public function test_date_path_options_hide_dates_behind_modules_the_user_cannot_view(): void
    {
        $through = fn (array $options): array => array_filter(array_keys($options), fn (string $path): bool => str_contains($path, '.'));

        $open = $this->resolver()->datePathOptions(Shipment::class);
        $restricted = $this->resolver()->datePathOptions(Shipment::class, $this->userWithPermissions([]));

        $this->assertNotEmpty($through($open));
        $this->assertSame([], $through($restricted));
    }

    public function test_validate_path_rejects_empty_trailing_and_double_dot_paths(): void
    {
        foreach (['', 'pr_number.', '.pr_number', 'items..product'] as $path) {
            try {
                $this->resolver()->validatePath(PurchaseRequest::class, $path);
                $this->fail("Malformed path accepted: '{$path}'");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_sensitive_user_columns_are_not_exposed_even_through_relations(): void
    {
        $columns = $this->resolver()->columns(User::class);

        foreach (['password', 'remember_token', 'ip', 'phone', 'email', 'settings', 'image'] as $blocked) {
            $this->assertArrayNotHasKey($blocked, $columns);
        }

        $this->assertArrayHasKey('name', $columns);
    }

    public function test_relationship_selectors_hide_modules_the_user_cannot_view(): void
    {
        $names = fn (array $constraints): array => array_map(fn (Constraint $constraint): string => $constraint->getName(), $constraints);

        $open = $names($this->resolver()->constraintsFor(PurchaseRequest::class));
        $restricted = $names($this->resolver()->constraintsFor(PurchaseRequest::class, [], User::factory()->make()));

        $this->assertContains('requester', $open);
        $this->assertNotContains('requester', $restricted);
        $this->assertNotContains('requester.name', $restricted);
    }

    public function test_range_anchor_falls_back_to_today_for_invalid_input(): void
    {
        foreach (['garbage', '2026-02-30', ''] as $input) {
            $this->assertSame(today()->toDateString(), CalendarRange::forMonth($input, false)->anchor);
        }
    }

    public function test_jalali_shift_handles_zero_and_large_deltas_symmetrically(): void
    {
        $iso = '2026-10-08';

        $this->assertSame($iso, CalendarRange::shiftMonths($iso, 0, true));

        foreach ([2, 12, 13, 25] as $delta) {
            $this->assertSame($iso, CalendarRange::shiftMonths(CalendarRange::shiftMonths($iso, $delta, true), -$delta, true));
        }
    }

    public function test_shift_months_without_overflow_in_both_calendars(): void
    {
        $this->assertSame('2026-11-30', CalendarRange::shiftMonths('2026-10-31', 1, false));
        $this->assertSame('2026-04-21', CalendarRange::shiftMonths('2026-03-21', 1, true));
        $this->assertSame(
            (new Jalalian(1404, 12, 1))->toCarbon()->toDateString(),
            CalendarRange::shiftMonths('2026-03-21', -1, true),
        );
    }

    // CalendarPresenter

    private function hitOn(string $date, array $ruleOverrides = [], array $hitOverrides = []): CalendarHit
    {
        $rule = CalendarRule::factory()->create($ruleOverrides);

        return CalendarHit::factory()->create(array_merge([
            'calendar_rule_id' => $rule->id,
            'event_date' => $date,
        ], $hitOverrides));
    }

    public function test_month_cells_flag_today_selected_and_overdue_action_days(): void
    {
        $this->hitOn('2026-10-12', ['color' => CalendarColor::TEAL->value]);
        $this->hitOn('2026-10-02', ['type' => RuleType::ACTION->value]);
        $this->hitOn('2026-10-08', ['type' => RuleType::ACTION->value]);

        $cells = collect((new CalendarPresenter)->monthCells(
            CalendarRange::forMonth('2026-10-08', false),
            '2026-10-12',
            CalendarHit::query()->get(),
        ))->keyBy('iso');

        $this->assertSame(1, $cells['2026-10-12']['count']);
        $this->assertSame(['teal'], $cells['2026-10-12']['colors']);
        $this->assertTrue($cells['2026-10-12']['isSelected']);
        $this->assertTrue($cells['2026-10-08']['isToday']);
        $this->assertFalse($cells['2026-10-08']['hasOverdue']);
        $this->assertTrue($cells['2026-10-02']['isPast']);
        $this->assertTrue($cells['2026-10-02']['hasOverdue']);
    }

    public function test_agenda_lists_only_hit_days_with_overdue_actions_first(): void
    {
        $this->hitOn('2026-10-02', ['type' => RuleType::ACTION->value], ['label' => 'Zebra case']);
        $this->hitOn('2026-10-02', [], ['label' => 'Alpha case']);

        $days = (new CalendarPresenter)->agendaDays(
            CalendarRange::forMonth('2026-10-08', false),
            CalendarHit::query()->get(),
        );

        $this->assertCount(1, $days);
        $this->assertSame('2026-10-02', $days[0]['iso']);
        $this->assertSame('Zebra case', $days[0]['hits'][0]['label']);
        $this->assertTrue($days[0]['hits'][0]['overdue']);
        $this->assertFalse($days[0]['hits'][1]['overdue']);
    }

    public function test_jalali_month_cells_carry_jalali_day_numbers(): void
    {
        $this->hitOn('2026-10-08');

        $cells = collect((new CalendarPresenter)->monthCells(
            CalendarRange::forMonth('2026-10-08', true),
            '2026-10-08',
            CalendarHit::query()->get(),
        ))->keyBy('iso');

        $this->assertSame(16, $cells['2026-10-08']['day']);
    }

    public function test_weekday_labels_start_saturday_jalali_and_monday_gregorian(): void
    {
        $presenter = new CalendarPresenter;

        $this->assertSame('شنبه', $presenter->weekdayLabels(true)[0]);
        $this->assertSame('Monday', $presenter->weekdayLabels(false)[0]);
    }

    public function test_mini_month_builds_localized_year_and_month_options(): void
    {
        $presenter = new CalendarPresenter;

        $jalali = $presenter->miniMonth('2026-10-08', true);
        $gregorian = $presenter->miniMonth('2026-10-08', false);

        $this->assertSame(1405, $jalali['currentYear']);
        $this->assertSame(7, $jalali['currentMonth']);
        $this->assertSame('فروردین', $jalali['months'][0]);
        $this->assertContains('1405', $jalali['years']);

        $this->assertSame(2026, $gregorian['currentYear']);
        $this->assertSame(10, $gregorian['currentMonth']);
        $this->assertSame('January', $gregorian['months'][0]);
    }

    // CalendarEngine — syncRule

    private function router(): CalendarRouter
    {
        return new CalendarRouter($this->resolver(), new CalendarFilterTree);
    }

    private function engine(?CalendarPathResolver $resolver = null): CalendarEngine
    {
        return new CalendarEngine($resolver ?? $this->resolver(), new CalendarActivity, new CalendarFilterTree);
    }

    private function alerts(): CalendarAlerts
    {
        return new CalendarAlerts(new CalendarActivity);
    }

    /**
     * @return array{rules: array<int, array<string, mixed>>}
     */
    private function filterOn(string $needle): array
    {
        return ['rules' => [
            ['type' => 'pr_number', 'data' => ['operator' => 'contains', 'settings' => ['text' => $needle]]],
        ]];
    }

    /**
     * @return Collection<int, PurchaseRequest>
     */
    private function createMatchingRequests(int $count, string $needle, string $date = '2026-10-20'): Collection
    {
        return collect(range(1, $count))->map(function () use ($needle, $date): PurchaseRequest {
            $request = PurchaseRequest::factory()->create();
            PurchaseRequest::query()->whereKey($request->id)->update([
                'pr_number' => $needle.'-'.uniqid(),
                'required_by_date' => $date,
            ]);

            return $request;
        });
    }

    /**
     * @param  Collection<int, PurchaseRequest>|array<int, PurchaseRequest>  $requests
     */
    private function activityCountFor(Collection|array $requests, string $event): int
    {
        return DB::table(config('activitylog.table_name'))
            ->where('log_name', 'calendar')
            ->where('description', $event)
            ->whereIn('subject_id', collect($requests)->pluck('id'))
            ->where('subject_type', (new PurchaseRequest)->getMorphClass())
            ->count();
    }

    public function test_sync_rule_matches_records_and_logs_transitions_only(): void
    {
        $needle = 'CALSUBJ'.uniqid();
        $requests = $this->createMatchingRequests(3, $needle);
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);

        $this->engine()->syncRule($rule);

        $hits = CalendarHit::query()->where('calendar_rule_id', $rule->id)->get();

        $this->assertCount(3, $hits);
        $this->assertEqualsCanonicalizing(
            $requests->pluck('id')->all(),
            $hits->pluck('subject_id')->all(),
        );
        $this->assertSame('2026-10-20', $hits->first()->event_date->toDateString());
        $this->assertEquals([], $hits->first()->alerts_sent);
        $this->assertSame(3, $this->activityCountFor($requests, 'matched'));

        $this->engine()->syncRule($rule);

        $this->assertSame(3, $this->activityCountFor($requests, 'matched'));
        $this->assertSame(0, $this->activityCountFor($requests, 'date_changed'));
        $this->assertSame(0, $this->activityCountFor($requests, 'cleared'));
    }

    public function test_sync_rule_runs_a_constant_number_of_queries(): void
    {
        $needleA = 'CALQA'.uniqid();
        $needleB = 'CALQB'.uniqid();
        $ruleA = CalendarRule::factory()->create(['filters' => $this->filterOn($needleA)]);
        $ruleB = CalendarRule::factory()->create(['filters' => $this->filterOn($needleB)]);
        $resolver = $this->resolver();
        $resolver->constraintsForRule($ruleA); // warm the schema caches before measuring

        $this->createMatchingRequests(3, $needleA);
        $this->createMatchingRequests(6, $needleB);

        $engine = $this->engine($resolver);
        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        $engine->syncRule($ruleA);
        $countA = count($connection->getQueryLog());
        $connection->flushQueryLog();
        $engine->syncRule($ruleB);
        $countB = count($connection->getQueryLog());
        $connection->disableQueryLog();

        $this->assertSame($countA, $countB);
        $this->assertSame(3, CalendarHit::query()->where('calendar_rule_id', $ruleA->id)->count());
        $this->assertSame(6, CalendarHit::query()->where('calendar_rule_id', $ruleB->id)->count());
    }

    public function test_sync_rule_re_arms_alerts_when_event_date_changes(): void
    {
        $needle = 'CALRM'.uniqid();
        $requests = $this->createMatchingRequests(3, $needle);
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);

        $this->engine()->syncRule($rule);

        $first = $requests->first();
        $hit = CalendarHit::query()->where('subject_id', $first->id)->first();
        $hit->update(['alerts_sent' => ['7' => '2026-10-08'], 'overdue_count' => 2]);

        PurchaseRequest::query()->whereKey($first->id)->update(['required_by_date' => '2026-10-25']);
        $this->engine()->syncRule($rule);

        $hit = CalendarHit::query()->where('subject_id', $first->id)->first();

        $this->assertSame('2026-10-25', $hit->event_date->toDateString());
        $this->assertEquals([], $hit->alerts_sent);
        $this->assertSame(0, $hit->overdue_count);
        $this->assertSame(1, $this->activityCountFor([$first], 'date_changed'));
    }

    public function test_inactive_rule_purges_hits_and_logs_cleared(): void
    {
        $needle = 'CALOFF'.uniqid();
        $requests = $this->createMatchingRequests(2, $needle);
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);

        $this->engine()->syncRule($rule);
        $this->assertSame(2, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());

        CalendarRule::query()->whereKey($rule->id)->update(['is_active' => false]);
        $this->engine()->syncRule($rule->fresh());

        $this->assertSame(0, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());
        $this->assertSame(2, $this->activityCountFor($requests, 'cleared'));
    }

    // CalendarEngine — syncSubject

    public function test_sync_subject_creates_and_clears_hits_by_current_match(): void
    {
        $needle = 'CALSUB'.uniqid();
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);
        $engine = $this->engine();

        $kept = $this->createMatchingRequests(1, $needle)->first();
        $engine->syncSubject(PurchaseRequest::class, $kept->id);
        $this->assertSame(1, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());

        PurchaseRequest::query()->whereKey($kept->id)->update(['pr_number' => 'NOMATCH'.uniqid()]);
        $engine->syncSubject(PurchaseRequest::class, $kept->id);

        $this->assertSame(0, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());
        $this->assertSame(1, $this->activityCountFor([$kept], 'cleared'));

        $deleted = $this->createMatchingRequests(1, $needle)->first();
        $engine->syncSubject(PurchaseRequest::class, $deleted->id);
        $this->assertSame(1, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());

        $deleted->delete();
        $engine->syncSubject(PurchaseRequest::class, $deleted->id);

        $this->assertSame(0, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());
    }

    // CalendarEngine — touch

    public function test_touch_ignores_unwatched_columns_unrelated_classes_and_suspension(): void
    {
        $needle = 'CALTX'.uniqid();
        $request = $this->createMatchingRequests(1, $needle)->first();
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);
        $rule->watched_columns = $this->router()->computeWatchedColumns($rule);
        $rule->save();

        Queue::fake();

        $fresh = $request->fresh();
        $fresh->notes = 'unwatched change';
        $fresh->save();
        $this->router()->touch($fresh, 'updated');
        Queue::assertNothingPushed();

        $this->router()->touch(User::factory()->create(), 'created');
        Queue::assertNothingPushed();

        CalendarRouter::suspended(fn () => $this->router()->touch($fresh, 'created'));
        Queue::assertNothingPushed();
    }

    public function test_touch_dispatches_subject_sync_for_watched_changes_and_structural_events(): void
    {
        $needle = 'CALTC'.uniqid();
        $request = $this->createMatchingRequests(1, $needle)->first();
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);
        $rule->watched_columns = $this->router()->computeWatchedColumns($rule);
        $rule->save();

        Queue::fake();

        $fresh = $request->fresh();
        $fresh->required_by_date = '2026-11-05';
        $fresh->save(); // the touch observer dispatches through the real wiring

        Queue::assertPushed(SyncCalendarSubject::class, 1);
        Queue::assertPushed(SyncCalendarSubject::class, fn (SyncCalendarSubject $job): bool => $job->uniqueId() === PurchaseRequest::class.':'.$request->id);

        Queue::fake();
        $this->router()->touch($request->fresh(), 'created');
        Queue::assertPushed(SyncCalendarSubject::class, 1);
    }

    public function test_touch_reverse_routes_related_class_changes_to_affected_subjects(): void
    {
        $requester = $this->userWithPermissions([]);
        $request = PurchaseRequest::factory()->create(['requester_id' => $requester->id]);
        $rule = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'requester.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => $requester->name]]],
        ]]]);
        $rule->watched_columns = $this->router()->computeWatchedColumns($rule);
        $rule->save();

        Queue::fake();

        $requester->name = $requester->name.' Jr';
        $requester->save(); // the touch observer reverse-routes through the real wiring

        Queue::assertPushed(SyncCalendarSubject::class, 1);
        Queue::assertPushed(SyncCalendarSubject::class, fn (SyncCalendarSubject $job): bool => $job->class === PurchaseRequest::class && $job->id === $request->id);
    }

    private function requesterRule(User $requester, string $needle): CalendarRule
    {
        $rule = CalendarRule::factory()->create([
            'subject' => ProformaInvoice::class,
            'date_path' => 'validity_date',
            'extra_paths' => [],
            'filters' => ['rules' => [
                ['type' => 'invoice_no', 'data' => ['operator' => 'contains', 'settings' => ['text' => $needle]]],
                ['type' => 'purchaseRequests.requester', 'data' => ['operator' => 'isRelatedTo', 'settings' => ['value' => $requester->id]]],
            ]],
        ]);
        $rule->watched_columns = $this->router()->computeWatchedColumns($rule);
        $rule->save();

        return $rule;
    }

    public function test_a_linked_table_offers_foreign_key_selectors_with_module_prefixed_labels(): void
    {
        $user = $this->userWithPermissions(['proforma_invoice.view', 'purchase_request.view', 'user.view', 'department.view']);

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $byName = collect($this->resolver()->constraintsFor(ProformaInvoice::class, ['purchaseRequests'], $user))->keyBy(fn (Constraint $constraint): string => $constraint->getName());
            $prefix = CalendarModules::label(PurchaseRequest::class).' › ';

            foreach (['requester', 'department'] as $relation) {
                $this->assertInstanceOf(RelationshipConstraint::class, $byName["purchaseRequests.{$relation}"], $locale);
                $this->assertStringStartsWith($prefix, (string) $byName["purchaseRequests.{$relation}"]->getLabel(), $locale);
                $this->assertDoesNotMatchRegularExpression('/_|[a-z][A-Z]/', (string) $byName["purchaseRequests.{$relation}"]->getLabel(), $locale);
            }

            $this->assertSame($prefix.trim(Str::before(__('resources/purchaseRequest/strings.form.requester'), ' — ')), (string) $byName['purchaseRequests.requester']->getLabel());
            $this->assertArrayNotHasKey('purchaseRequests.requester_id', $byName->all());
            $this->assertArrayNotHasKey('purchaseRequests.creator', $byName->all());
        }
    }

    public function test_linked_selectors_hide_unviewable_targets_and_derive_the_parent_table_only(): void
    {
        $user = $this->userWithPermissions(['proforma_invoice.view', 'purchase_request.view', 'department.view']);
        $names = array_map(fn (Constraint $constraint): string => $constraint->getName(), $this->resolver()->constraintsFor(ProformaInvoice::class, ['purchaseRequests'], $user));

        $this->assertContains('purchaseRequests.department', $names);
        $this->assertNotContains('purchaseRequests.requester', $names);

        $kept = fn (User $viewer): array => array_map(fn (Constraint $constraint): string => $constraint->getName(), $this->resolver()->constraintsFor(ProformaInvoice::class, ['purchaseRequests'], $viewer, ['purchaseRequests.requester_id']));
        $this->assertNotContains('purchaseRequests.requester_id', $kept($user));
        $this->assertContains('purchaseRequests.requester_id', $kept($this->userWithPermissions(['proforma_invoice.view', 'purchase_request.view', 'user.view'])));

        $leaf = ['type' => 'purchaseRequests.requester', 'data' => ['operator' => 'isRelatedTo', 'settings' => ['value' => 1]]];
        $this->assertSame(['purchaseRequests'], $this->resolver()->usedPaths(ProformaInvoice::class, [$leaf]));
        $this->assertSame([PurchaseRequest::class, User::class], $this->resolver()->relatedModels(CalendarRule::factory()->make(['subject' => ProformaInvoice::class, 'date_path' => 'invoice_date', 'filters' => ['rules' => [$leaf]]])));
    }

    public function test_a_warm_resolver_builds_constraints_without_schema_queries(): void
    {
        $user = $this->userWithPermissions(['proforma_invoice.view', 'purchase_request.view']);
        $resolver = $this->resolver();
        $resolver->constraintsFor(ProformaInvoice::class, ['purchaseRequests'], $user);

        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        $resolver->constraintsFor(ProformaInvoice::class, ['purchaseRequests'], $user);
        $schemaQueries = array_filter($connection->getQueryLog(), fn (array $query): bool => Str::contains(Str::lower($query['query']), ['information_schema', 'show ']));
        $connection->disableQueryLog();

        $this->assertSame([], array_values($schemaQueries));
    }

    public function test_a_linked_requester_rule_matches_only_invoices_linked_to_that_requesters_request(): void
    {
        $needle = 'CALPIR'.uniqid();
        $ali = User::factory()->create();
        $other = User::factory()->create();
        $make = function (User $requester) use ($needle): ProformaInvoice {
            $invoice = ProformaInvoice::factory()->create();
            ProformaInvoice::query()->whereKey($invoice->id)->update(['invoice_no' => $needle.uniqid()]);
            $invoice->purchaseRequests()->attach(PurchaseRequest::factory()->create(['requester_id' => $requester->id])->id);

            return $invoice;
        };
        $mine = $make($ali);
        $make($other);
        $rule = $this->requesterRule($ali, $needle);

        $this->assertSame([], $this->engine()->filterProblems($rule));
        $this->engine()->syncRule($rule);

        $this->assertSame([$mine->id], CalendarHit::query()->where('calendar_rule_id', $rule->id)->pluck('subject_id')->all());
        $this->assertStringContainsString($ali->name, $this->resolver()->sentence(ProformaInvoice::class, [], $rule->filters['rules']));
        $this->assertStringContainsString($ali->name, implode(' ', $this->resolver()->summaries($rule)));
    }

    public function test_an_own_foreign_key_selector_rule_matches_by_record(): void
    {
        $ali = User::factory()->create();
        $mine = PurchaseRequest::factory()->create(['requester_id' => $ali->id]);
        PurchaseRequest::factory()->create();
        $rule = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'requester', 'data' => ['operator' => 'isRelatedTo', 'settings' => ['value' => $ali->id]]],
        ]]]);

        $this->assertSame([], $this->engine()->filterProblems($rule));
        $this->assertTrue($this->engine()->syncRule($rule));
        $this->assertSame([$mine->id], CalendarHit::query()->where('calendar_rule_id', $rule->id)->pluck('subject_id')->all());
    }

    public function test_changing_the_linked_requests_requester_reroutes_the_invoice(): void
    {
        $needle = 'CALPIW'.uniqid();
        $ali = User::factory()->create();
        $invoice = ProformaInvoice::factory()->create();
        ProformaInvoice::query()->whereKey($invoice->id)->update(['invoice_no' => $needle]);
        $request = PurchaseRequest::factory()->create(['requester_id' => $ali->id]);
        $invoice->purchaseRequests()->attach($request->id);
        $rule = $this->requesterRule($ali, $needle);

        $this->assertContains('requester_id', $rule->watched_columns[PurchaseRequest::class]);

        Queue::fake();
        $request->requester_id = User::factory()->create()->id;
        $request->save();

        Queue::assertPushed(SyncCalendarSubject::class, fn (SyncCalendarSubject $job): bool => $job->class === ProformaInvoice::class && $job->id === $invoice->id);
    }

    public function test_a_linked_selector_leaf_on_a_hostile_path_is_rejected(): void
    {
        $leaf = fn (string $type): array => ['type' => $type, 'data' => ['operator' => 'isRelatedTo', 'settings' => ['value' => 1]]];

        foreach (['purchaseRequests.requester.nope', 'nope.requester', 'purchaseRequests.', '.requester'] as $type) {
            $rule = CalendarRule::factory()->make(['subject' => ProformaInvoice::class, 'extra_paths' => [], 'filters' => ['rules' => [$leaf($type)]]]);

            $this->assertNotSame([], $this->engine()->filterProblems($rule), $type);
        }
    }

    public function test_compute_watched_columns_covers_filters_date_and_hop_keys(): void
    {
        $rule = CalendarRule::factory()->make([
            'subject' => PurchaseRequest::class,
            'filters' => ['rules' => [
                ['type' => 'items.product.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'X']]],
                ['type' => 'requester', 'data' => ['operator' => 'equals', 'settings' => ['value' => 1]]],
            ]],
            'date_path' => 'required_by_date',
        ]);

        $watched = $this->router()->computeWatchedColumns($rule);

        $this->assertEqualsCanonicalizing(['deleted_at', 'required_by_date', 'requester_id'], $watched[PurchaseRequest::class]);
        $this->assertEqualsCanonicalizing(['purchase_request_id', 'product_id', 'deleted_at'], $watched[PurchaseRequestItem::class]);
        $this->assertEqualsCanonicalizing(['name', 'deleted_at'], $watched[Product::class]);
        $this->assertEqualsCanonicalizing(['deleted_at'], $watched[User::class]);
    }

    // Observers (Step 5)

    public function test_saving_a_rule_computes_fingerprints_watched_columns_and_bumps_the_routing_version(): void
    {
        $version = Cache::get('calendar_routes_version');
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn('CALOBS')]);

        $this->assertNotEmpty($rule->fingerprint);
        $this->assertNotEmpty($rule->conditions_hash);
        $this->assertArrayHasKey(PurchaseRequest::class, $rule->watched_columns);
        $this->assertNotSame($version, Cache::get('calendar_routes_version'));
        Queue::assertPushed(SyncCalendarRule::class, fn (SyncCalendarRule $job): bool => $job->ruleId === $rule->id);

        $this->assertSame(1, DB::table(config('activitylog.table_name'))
            ->where('log_name', 'calendar')
            ->where('description', 'rule_updated')
            ->where('subject_type', (new CalendarRule)->getMorphClass())
            ->where('subject_id', $rule->id)
            ->count());
    }

    public function test_a_new_rule_syncs_at_once_while_an_edit_is_debounced_for_two_minutes(): void
    {
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn('CALDEL')]);
        Queue::assertPushed(SyncCalendarRule::class, fn (SyncCalendarRule $job): bool => $job->ruleId === $rule->id && $job->delay === null);

        Queue::fake();
        $rule->fresh()->update(['day_shift' => $rule->day_shift + 1]);

        Queue::assertPushed(SyncCalendarRule::class, fn (SyncCalendarRule $job): bool => $job->ruleId === $rule->id && $job->delay === 120);
    }

    public function test_deleting_a_rule_dispatches_and_purges_its_hits(): void
    {
        $rule = CalendarRule::factory()->create();
        $hit = CalendarHit::factory()->create(['calendar_rule_id' => $rule->id, 'event_date' => '2026-10-20']);
        Queue::fake();

        $rule->delete();

        $this->assertTrue($rule->trashed());
        Queue::assertPushed(SyncCalendarRule::class, fn (SyncCalendarRule $job): bool => $job->ruleId === $rule->id);

        (new SyncCalendarRule($rule->id))->handle($this->engine());

        $this->assertSame(0, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());
        $this->assertSame(1, DB::table(config('activitylog.table_name'))
            ->where('log_name', 'calendar')
            ->where('description', 'cleared')
            ->where('subject_type', $hit->subject_type)
            ->where('subject_id', $hit->subject_id)
            ->count());
    }

    public function test_suspension_blocks_the_observer_dispatches(): void
    {
        $needle = 'CALSUS'.uniqid();
        $request = $this->createMatchingRequests(1, $needle)->first();
        CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);
        Queue::fake();

        CalendarRouter::suspended(function () use ($request): void {
            $request->fresh()->update(['required_by_date' => '2026-12-01']);
        });
        Queue::assertNothingPushed();

        CalendarRouter::suspended(fn (): CalendarRule => CalendarRule::factory()->create());
        Queue::assertNothingPushed();
    }

    // CalendarEngine — preview and event dates

    public function test_preview_counts_matches_and_caps_items(): void
    {
        $needle = 'CALPV'.uniqid();
        $this->createMatchingRequests(6, $needle);
        $user = $this->userWithPermissions(['purchase_request.view']);

        $preview = $this->engine()->preview($user, PurchaseRequest::class, $this->filterOn($needle), [], 'required_by_date', 0);

        $this->assertSame(6, $preview['count']);
        $this->assertCount(5, $preview['items']);
        $this->assertSame('2026-10-20', $preview['items'][0]['date']);
    }

    public function test_preview_rejects_unviewable_subjects_and_hostile_payloads(): void
    {
        $permitted = $this->userWithPermissions(['purchase_request.view']);
        $plain = $this->userWithPermissions([]);
        $engine = $this->engine();

        try {
            $engine->preview($plain, PurchaseRequest::class, $this->filterOn('x'), [], 'required_by_date', 0);
            $this->fail('Unviewable subject accepted.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $hostile = [
            ['rules' => 'nope'],
            ['rules' => ['not-an-array']],
            ['rules' => [['data' => ['operator' => 'equals']]]],
            ['rules' => [['type' => 'ghost_column', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'X']]]]],
            ['rules' => [['type' => 'pr_number', 'data' => ['operator' => 'bogus', 'settings' => ['text' => 'X']]]]],
        ];

        foreach ($hostile as $filters) {
            try {
                $engine->preview($permitted, PurchaseRequest::class, $filters, [], 'required_by_date', 0);
                $this->fail('Hostile payload accepted: '.json_encode($filters));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        foreach (['', 'typo.path', 'pr_number'] as $datePath) {
            try {
                $engine->preview($permitted, PurchaseRequest::class, $this->filterOn('x'), [], $datePath, 0);
                $this->fail("Invalid date path accepted: '{$datePath}'");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $needle = 'CALPVP'.uniqid();
        $this->createMatchingRequests(1, $needle);

        $this->assertSame(1, $engine->preview($permitted, PurchaseRequest::class, $this->filterOn($needle), [], 'required_by_date', 0)['count']);
    }

    public function test_event_date_for_applies_shift_and_handles_missing_values(): void
    {
        $request = PurchaseRequest::factory()->create(['user_id' => User::factory()]);
        PurchaseRequest::query()->whereKey($request->id)->update(['required_by_date' => '2026-10-20']);
        $engine = $this->engine();

        $this->assertSame('2026-10-22', $engine->eventDateFor($request->fresh(), 'required_by_date', 2)->toDateString());
        $this->assertSame('2026-10-08', $engine->eventDateFor($request->fresh(), 'creator.created_at', 0)->toDateString());

        PurchaseRequest::query()->whereKey($request->id)->update(['required_by_date' => null]);
        $this->assertNull($engine->eventDateFor($request->fresh(), 'required_by_date', 0));
    }

    // CalendarAlerts — sendDue fan-out

    public function test_send_due_fans_out_one_job_per_active_rule(): void
    {
        $activeA = CalendarRule::factory()->create();
        $activeB = CalendarRule::factory()->create();
        $inactive = CalendarRule::factory()->create(['is_active' => false]);

        Queue::fake();

        $this->alerts()->sendDue(today());

        Queue::assertPushed(SendCalendarRuleAlerts::class, 2);
        Queue::assertPushed(SendCalendarRuleAlerts::class, fn (SendCalendarRuleAlerts $job): bool => $job->ruleId === $activeA->id && $job->day === '2026-10-08');
        Queue::assertPushed(SendCalendarRuleAlerts::class, fn (SendCalendarRuleAlerts $job): bool => $job->ruleId === $activeB->id && $job->day === '2026-10-08');
        Queue::assertNotPushed(SendCalendarRuleAlerts::class, fn (SendCalendarRuleAlerts $job): bool => $job->ruleId === $inactive->id);
    }

    // CalendarAlerts — leads, on-day, re-arm, collapse

    public function test_lead_alerts_fire_once_collapse_missed_days_and_rearm(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $hit = $this->hitOn('2026-10-11', ['user_id' => $owner->id, 'lead_times' => [7, 3], 'on_day' => false]);
        $rule = $hit->rule;

        Notification::fake();

        $this->alerts()->sendForRule($rule, today());

        Notification::assertSentTo($owner, CalendarAlertNotification::class, fn (CalendarAlertNotification $n): bool => $n->entries->first()['leads'] === [7, 3]);

        $this->alerts()->sendForRule($rule, today());
        Notification::assertSentTimes(CalendarAlertNotification::class, 1);

        CalendarHit::query()->whereKey($hit->id)->update(['event_date' => '2026-10-15', 'alerts_sent' => '[]']);

        $this->alerts()->sendForRule($rule, today());

        Notification::assertSentTo($owner, CalendarAlertNotification::class, fn (CalendarAlertNotification $n): bool => $n->entries->first()['leads'] === [7]);
        Notification::assertSentTimes(CalendarAlertNotification::class, 2);
    }

    public function test_notification_type_selects_channels_and_mail_only_rules_do_not_double_send(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $hit = $this->hitOn('2026-10-11', ['user_id' => $owner->id, 'lead_times' => [3], 'on_day' => false, 'notification_type' => 'email']);
        $rule = $hit->rule;
        $entries = collect([['hit' => $hit, 'leads' => [3], 'on_day' => false, 'overdue' => false]]);

        $this->assertSame(['mail'], (new CalendarAlertNotification($rule, $entries, '2026-10-08'))->via($owner));
        $this->assertSame(['database', 'mail'], (new CalendarAlertNotification($rule->forceFill(['notification_type' => 'all']), $entries, '2026-10-08'))->via($owner));
        $this->assertSame(['database'], (new CalendarAlertNotification($rule->forceFill(['notification_type' => 'in_app']), $entries, '2026-10-08'))->via($owner));
        $rule->forceFill(['notification_type' => 'email']);

        Notification::fake();

        $this->alerts()->sendForRule($rule, today());
        CalendarHit::query()->whereKey($hit->id)->update(['alerts_sent' => '[]']);
        $this->alerts()->sendForRule($rule->fresh(), today());

        Notification::assertSentTimes(CalendarAlertNotification::class, 1);
    }

    public function test_a_failed_mail_only_send_is_retried_instead_of_being_guarded_away(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $hit = $this->hitOn('2026-10-08', ['user_id' => $owner->id, 'lead_times' => [], 'on_day' => true, 'notification_type' => 'email']);
        $rule = $hit->rule;

        $manager = Mockery::mock(ChannelManager::class);
        $manager->shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));
        $manager->shouldReceive('send')->once()->withArgs(fn (User $user): bool => $user->is($owner));
        Notification::swap($manager);

        try {
            $this->alerts()->sendForRule($rule, today());
            $this->fail('The failed send was swallowed.');
        } catch (RuntimeException) {
            $this->assertSame([], $hit->fresh()->alerts_sent);
        }

        $this->alerts()->sendForRule($rule, today());

        $this->assertSame('2026-10-08', $hit->fresh()->alerts_sent[0]);
        $this->assertTrue(Cache::has("calendar_alert_mail:{$rule->id}:2026-10-08:{$owner->id}"));
    }

    public function test_on_day_alert_fires_once_and_today_is_not_overdue(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $hit = $this->hitOn('2026-10-08', ['user_id' => $owner->id, 'lead_times' => [], 'on_day' => true]);
        $rule = $hit->rule;

        Notification::fake();

        $this->alerts()->sendForRule($rule, today());
        $this->alerts()->sendForRule($rule, today());

        Notification::assertSentTo($owner, CalendarAlertNotification::class, fn (CalendarAlertNotification $n): bool => $n->entries->first()['on_day'] && ! $n->entries->first()['overdue']);
        Notification::assertSentTimes(CalendarAlertNotification::class, 1);
    }

    // CalendarAlerts — overdue cadence

    public function test_overdue_alerts_cadence_caps_at_three_and_skips_heads_up(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $hit = $this->hitOn('2026-10-01', ['user_id' => $owner->id, 'type' => RuleType::ACTION->value, 'lead_times' => [], 'on_day' => false]);
        $rule = $hit->rule;
        $alerts = $this->alerts();

        Notification::fake();
        $alerts->sendForRule($rule, today());

        Notification::assertSentTo($owner, CalendarAlertNotification::class, fn (CalendarAlertNotification $n): bool => $n->entries->first()['overdue']);
        $this->assertSame(1, $hit->fresh()->overdue_count);
        $this->assertSame(['2026-10-08'], $hit->fresh()->alerts_sent['overdue']);

        foreach ([['2026-10-14', 1], ['2026-10-15', 2], ['2026-10-21', 2], ['2026-10-22', 3], ['2026-10-29', 3]] as [$date, $count]) {
            Carbon::setTestNow(Carbon::parse($date));
            $alerts->sendForRule($rule, today());
            $this->assertSame($count, $hit->fresh()->overdue_count, $date);
        }

        Notification::assertSentTimes(CalendarAlertNotification::class, 3);

        $headsUp = $this->hitOn('2026-10-01', ['user_id' => $owner->id, 'lead_times' => [], 'on_day' => false]);
        $alerts->sendForRule($headsUp->rule, today());
        Notification::assertSentTimes(CalendarAlertNotification::class, 3);
    }

    public function test_zero_recipients_marks_nothing_until_access_is_granted(): void
    {
        $owner = $this->userWithPermissions([]); // no module permission → no recipients
        $hit = $this->hitOn('2026-10-11', ['user_id' => $owner->id, 'lead_times' => [7], 'on_day' => false]);
        $rule = $hit->rule;

        Notification::fake();

        $this->alerts()->sendForRule($rule, today());

        Notification::assertNothingSentTo($owner);
        $this->assertSame([], $hit->fresh()->alerts_sent);
        $this->assertSame(0, DB::table(config('activitylog.table_name'))
            ->where('description', 'alert_sent')
            ->where('subject_id', $hit->subject_id)
            ->count());

        $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'purchase_request.view', 'guard_name' => 'web']));
        $owner->fresh()->assignRole($role);

        $this->alerts()->sendForRule($rule, today());

        Notification::assertSentTo($owner->fresh(), CalendarAlertNotification::class);
        $this->assertSame('2026-10-08', $hit->fresh()->alerts_sent[7]);
    }

    public function test_alert_recipients_are_filtered_by_module_permission(): void
    {
        $permitted = $this->userWithPermissions(['purchase_request.view']);
        $plain = $this->userWithPermissions([]);
        $hit = $this->hitOn('2026-10-11', [
            'user_id' => $permitted->id,
            'visibility' => Visibility::EVERYONE->value,
            'lead_times' => [7],
            'on_day' => false,
        ]);

        Notification::fake();

        $this->alerts()->sendForRule($hit->rule, today());

        Notification::assertSentTo($permitted, CalendarAlertNotification::class);
        Notification::assertNotSentTo($plain, CalendarAlertNotification::class);
    }

    private function roleWith(array $permissions): Role
    {
        $role = Role::create(['name' => 'extra_'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        return $role;
    }

    public function test_roles_visibility_audience_is_active_role_members_with_the_module_view_permission(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $viewRole = $this->roleWith(['purchase_request.view']);
        $blindRole = $this->roleWith([]);
        $member = User::factory()->create()->assignRole($viewRole);
        $blind = User::factory()->create()->assignRole($blindRole);
        $inactive = User::factory()->create(['status' => 'inactive'])->assignRole($viewRole);
        $rule = CalendarRule::factory()->create(['user_id' => $owner->id, 'visibility' => 'roles', 'shared_role_ids' => [$viewRole->id, $blindRole->id, 999999]]);

        $this->assertSame([$member->id], $rule->recipients()->pluck('id')->all());
        $this->assertNotContains($owner->id, $rule->recipients()->pluck('id')->all());
        $this->assertNotContains($blind->id, $rule->recipients()->pluck('id')->all());
        $this->assertNotContains($inactive->id, $rule->recipients()->pluck('id')->all());
        $this->assertSame([], CalendarRule::factory()->create(['user_id' => $owner->id, 'visibility' => 'roles', 'shared_role_ids' => []])->recipients()->all());
    }

    public function test_visible_to_for_each_visibility_on_rule_and_hit_queries(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $role = $this->roleWith(['purchase_request.view']);
        $viewer = $this->userWithPermissions(['purchase_request.view'])->assignRole($role);
        $outsider = $this->userWithPermissions(['purchase_request.view']);
        $make = fn (array $attributes): CalendarHit => $this->hitOn('2026-10-11', ['user_id' => $owner->id, ...$attributes]);
        $me = $make(['visibility' => 'me']);
        $everyone = $make(['visibility' => 'everyone']);
        $users = $make(['visibility' => 'users', 'shared_user_ids' => [$viewer->id]]);
        $roles = $make(['visibility' => 'roles', 'shared_role_ids' => [$role->id]]);
        $stale = $make(['visibility' => 'users', 'shared_role_ids' => [$role->id]]);
        $ruleIds = fn (User $user): array => CalendarRule::query()->visibleTo($user->fresh())->pluck('id')->all();
        $hitIds = fn (User $user): array => CalendarHit::query()->visibleTo($user->fresh())->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$everyone->rule->id, $users->rule->id, $roles->rule->id], $ruleIds($viewer));
        $this->assertEqualsCanonicalizing([$everyone->rule->id], $ruleIds($outsider));
        $this->assertEqualsCanonicalizing(CalendarRule::query()->pluck('id')->all(), $ruleIds($owner));
        $this->assertEqualsCanonicalizing([$everyone->id, $users->id, $roles->id], $hitIds($viewer));
        $this->assertEqualsCanonicalizing([$everyone->id], $hitIds($outsider));
        $this->assertNotContains($me->id, $hitIds($viewer));
        $this->assertNotContains($stale->id, $hitIds($viewer));
        $this->assertNull($stale->rule->fresh()->shared_role_ids);
    }

    public function test_role_members_are_alerted_and_the_owner_only_when_in_the_audience(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $role = $this->roleWith(['purchase_request.view']);
        $member = User::factory()->create()->assignRole($role);
        $hit = $this->hitOn('2026-10-11', ['user_id' => $owner->id, 'visibility' => 'roles', 'shared_role_ids' => [$role->id], 'lead_times' => [7], 'on_day' => false]);

        Notification::fake();
        $this->alerts()->sendForRule($hit->rule, today());

        Notification::assertSentTo($member, CalendarAlertNotification::class);
        Notification::assertNotSentTo($owner, CalendarAlertNotification::class);
    }

    public function test_an_address_that_belongs_to_a_recipient_is_never_mailed_twice(): void
    {
        $role = $this->roleWith(['purchase_request.view']);
        $member = User::factory()->create(['email' => 'Member@Example.com'])->assignRole($role);
        $hit = $this->hitOn('2026-10-11', ['user_id' => $member->id, 'visibility' => 'roles', 'shared_role_ids' => [$role->id], 'notification_type' => 'email', 'lead_times' => [7], 'on_day' => false]);
        $hit->rule->forceFill(['notify_emails' => ['member@example.com', 'extra@example.com']])->saveQuietly();

        Notification::fake();
        $this->alerts()->sendForRule($hit->rule->fresh(), today());

        Notification::assertSentTo($member, CalendarAlertNotification::class);
        Notification::assertSentOnDemandTimes(CalendarAlertNotification::class, 1);
    }

    public function test_outside_emails_get_one_mail_per_rule_per_day_and_never_the_in_app_channel(): void
    {
        $owner = $this->userWithPermissions([]);
        $hit = $this->hitOn('2026-10-11', ['user_id' => $owner->id, 'notification_type' => 'all', 'notify_emails' => ['Out@Example.com', 'out@example.com', 'two@example.com'], 'lead_times' => [7], 'on_day' => false]);
        $rule = $hit->rule;
        $lastActivity = (int) DB::table(config('activitylog.table_name'))->max('id');

        Notification::fake();
        $this->alerts()->sendForRule($rule, today());
        CalendarHit::query()->whereKey($hit->id)->update(['alerts_sent' => '[]']);
        $this->alerts()->sendForRule($rule->fresh(), today());

        Notification::assertSentTimes(CalendarAlertNotification::class, 2);
        Notification::assertSentOnDemand(CalendarAlertNotification::class, fn (CalendarAlertNotification $n, array $channels, $notifiable): bool => $channels === ['mail'] && $notifiable->routes['mail'] === 'out@example.com');
        Notification::assertSentOnDemand(CalendarAlertNotification::class, fn (CalendarAlertNotification $n, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'two@example.com');
        $this->assertSame('2026-10-08', $hit->fresh()->alerts_sent[7]);
        $this->assertStringNotContainsString('example.com', json_encode(DB::table(config('activitylog.table_name'))->where('id', '>', $lastActivity)->get()));
    }

    public function test_outside_emails_are_ignored_when_the_channel_is_in_app_only(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $hit = $this->hitOn('2026-10-11', ['user_id' => $owner->id, 'notification_type' => 'in_app', 'notify_emails' => ['out@example.com'], 'lead_times' => [7], 'on_day' => false]);

        Notification::fake();
        $this->alerts()->sendForRule($hit->rule, today());

        Notification::assertSentTimes(CalendarAlertNotification::class, 1);
        Notification::assertSentOnDemandTimes(CalendarAlertNotification::class, 0);
    }

    public function test_a_failed_outside_email_send_is_retried_and_guarded_after_success(): void
    {
        $owner = $this->userWithPermissions([]);
        $hit = $this->hitOn('2026-10-08', ['user_id' => $owner->id, 'notification_type' => 'email', 'notify_emails' => ['out@example.com'], 'lead_times' => [], 'on_day' => true]);
        $rule = $hit->rule;

        $manager = Mockery::mock(ChannelManager::class);
        $manager->shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));
        $manager->shouldReceive('send')->once();
        Notification::swap($manager);

        try {
            $this->alerts()->sendForRule($rule, today());
            $this->fail('The failed send was swallowed.');
        } catch (RuntimeException) {
            $this->assertSame([], $hit->fresh()->alerts_sent);
        }

        $this->alerts()->sendForRule($rule, today());
        $this->alerts()->sendForRule($rule, today());

        $this->assertSame('2026-10-08', $hit->fresh()->alerts_sent[0]);
        $this->assertTrue(Cache::has("calendar_alert_mail:{$rule->id}:2026-10-08:e".sha1('out@example.com')));
    }

    public function test_hostile_outside_emails_are_dropped_and_the_list_is_capped(): void
    {
        $owner = $this->userWithPermissions([]);
        $many = array_map(fn (int $i): string => "user{$i}@example.com", range(1, 14));
        $rule = CalendarRule::factory()->create(['user_id' => $owner->id, 'notification_type' => 'email', 'notify_emails' => [
            "a@example.com\r\nBcc: evil@example.com", 'no-at-sign', '<x@example.com>', 'a b@example.com', str_repeat('a', 250).'@example.com', ['x@example.com'], 42, '"q"@example.com', ' Ok@Example.com ', ...$many,
        ]]);

        $stored = $rule->fresh()->notify_emails;

        $this->assertCount(10, $stored);
        $this->assertSame('ok@example.com', $stored[0]);
        $this->assertSame(['user1@example.com', 'user2@example.com'], array_slice($stored, 1, 2));
        $this->assertNull(CalendarRule::factory()->create(['user_id' => $owner->id, 'notification_type' => 'email', 'notify_emails' => ['bad', "x\ny@example.com"]])->fresh()->notify_emails);
    }

    // CalendarActivity — history tiers

    public function test_activity_history_tiers_and_seen_synthesis(): void
    {
        $activity = new CalendarActivity;
        $request = PurchaseRequest::factory()->create();
        $rule = CalendarRule::factory()->create(); // the observer logs rule_updated on save

        $activity->log('matched', $request, $rule, ['label' => 'PR-1', 'event_date' => '2026-10-20']);

        $this->assertSame(['matched'], $activity->forRecord($request)->pluck('event')->all());

        $hit = CalendarHit::factory()->forSubject($request)->create(['calendar_rule_id' => $rule->id]);
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\CalendarAlertNotification',
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => $rule->user_id,
            'data' => json_encode(['rule_id' => $rule->id, 'hit_ids' => [$hit->id]]),
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $events = $activity->forRecord($request, true)->pluck('event')->all();

        $this->assertContains('matched', $events);
        $this->assertContains('rule_updated', $events);
        $this->assertContains('seen', $events);
        $this->assertSame(3, count($events));
    }

    // §12 review fixes

    private function ruleInvalidCount(CalendarRule $rule): int
    {
        return DB::table(config('activitylog.table_name'))
            ->where('log_name', 'calendar')
            ->where('description', 'rule_invalid')
            ->where('subject_type', (new CalendarRule)->getMorphClass())
            ->where('subject_id', $rule->id)
            ->count();
    }

    public function test_stale_sweep_deletes_hits_for_records_that_no_longer_match(): void
    {
        $needle = 'CALSW'.uniqid();
        $request = $this->createMatchingRequests(1, $needle)->first();
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);

        $this->engine()->syncRule($rule);
        $this->assertSame(1, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());

        PurchaseRequest::query()->whereKey($request->id)->update(['pr_number' => 'GONE'.uniqid()]);
        Carbon::setTestNow(Carbon::parse('2026-10-09')); // §13.7: a hit touched in the scan's own second survives one run
        $this->engine()->syncRule($rule);

        $this->assertSame(0, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());
        $this->assertSame(1, $this->activityCountFor([$request], 'cleared'));
    }

    public function test_unknown_filter_leaf_or_operator_invalidates_the_rule_instead_of_matching_everything(): void
    {
        $request = $this->createMatchingRequests(1, 'CALINV'.uniqid())->first();
        $unknownType = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'renamed_column', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'X']]],
        ]]]);
        $unknownOperator = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'pr_number', 'data' => ['operator' => 'bogus', 'settings' => ['text' => 'X']]],
        ]]]);

        foreach ([[$unknownType, $request], [$unknownOperator, $request]] as [$rule, $subject]) {
            CalendarHit::factory()->forSubject($subject)->create(['calendar_rule_id' => $rule->id, 'event_date' => '2026-10-20']);
            $this->engine()->syncRule($rule);

            $this->assertSame(0, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());
            $this->assertSame(1, $this->ruleInvalidCount($rule), 'Exactly one rule_invalid row, never per record.');
        }
    }

    public function test_empty_filter_tree_matches_all_module_records(): void
    {
        $rule = CalendarRule::factory()->create(['filters' => ['rules' => []]]);
        $requests = $this->createMatchingRequests(2, 'CALEM'.uniqid());

        $this->engine()->syncRule($rule);

        $this->assertSame(2, CalendarHit::query()
            ->where('calendar_rule_id', $rule->id)
            ->whereIn('subject_id', $requests->pluck('id'))
            ->count());
    }

    public function test_unknown_subject_class_and_trashed_rules_are_treated_as_inactive(): void
    {
        $request = $this->createMatchingRequests(1, 'CALGH'.uniqid())->first();

        $ghost = CalendarRule::factory()->create(['subject' => Department::class, 'filters' => $this->filterOn('x')]);
        CalendarHit::factory()->forSubject($request)->create(['calendar_rule_id' => $ghost->id, 'event_date' => '2026-10-20']);
        $this->engine()->syncRule($ghost->fresh());
        $this->assertSame(0, CalendarHit::query()->where('calendar_rule_id', $ghost->id)->count());

        $trashed = CalendarRule::factory()->create(['filters' => $this->filterOn('x')]);
        CalendarHit::factory()->forSubject($request)->create(['calendar_rule_id' => $trashed->id, 'event_date' => '2026-10-20']);
        $trashed->delete();
        $this->engine()->syncRule(CalendarRule::withTrashed()->find($trashed->id));
        $this->assertSame(0, CalendarHit::query()->where('calendar_rule_id', $trashed->id)->count());
    }

    public function test_related_row_soft_deletes_still_route_to_their_subjects(): void
    {
        $requester = $this->userWithPermissions([]);
        $request = PurchaseRequest::factory()->create(['requester_id' => $requester->id]);
        $rule = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'requester.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => $requester->name]]],
        ]]]);
        $rule->watched_columns = $this->router()->computeWatchedColumns($rule);
        $rule->save();

        Queue::fake();

        $requester->delete();
        $this->router()->touch($requester, 'deleted');

        Queue::assertPushed(SyncCalendarSubject::class, fn (SyncCalendarSubject $job): bool => $job->class === PurchaseRequest::class && $job->id === $request->id);
    }

    public function test_a_related_row_nobody_references_yet_dispatches_nothing(): void
    {
        $rule = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'requester.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'x']]],
        ]]]);
        $rule->watched_columns = $this->router()->computeWatchedColumns($rule);
        $rule->save();

        Queue::fake();

        $this->router()->touch(User::factory()->create(), 'created');

        Queue::assertNothingPushed();
    }

    public function test_a_non_constraint_in_the_constraint_list_is_reported_not_fatal(): void
    {
        $engine = $this->engine();
        $problems = [];
        $args = [['type' => 'x', 'data' => ['operator' => 'equals']], collect(['x' => fn () => null]), &$problems];

        (new \ReflectionMethod($engine, 'leafProblems'))->invokeArgs($engine, $args);

        $this->assertSame(['unknown_type:x'], $problems);
    }

    public function test_a_stored_filter_with_a_non_array_leaf_is_an_invalid_rule(): void
    {
        $rule = CalendarRule::factory()->make(['filters' => ['rules' => ['zz']]]);

        $this->assertSame(['malformed_filters'], $this->engine()->filterProblems($rule));
    }

    public function test_a_rules_node_that_is_not_a_list_saves_and_is_an_invalid_rule(): void
    {
        $rule = CalendarRule::factory()->create(['filters' => ['rules' => 'zz']]);

        $this->assertSame(['malformed_filters'], $this->engine()->filterProblems($rule));
        $this->assertSame([PurchaseRequest::class], array_keys($rule->fresh()->watched_columns));
    }

    public function test_watched_columns_of_a_rule_without_a_resolvable_subject_are_empty(): void
    {
        $unset = new CalendarRule(['date_path' => 'eta', 'filters' => ['rules' => []]]);
        $gone = new CalendarRule(['subject' => 'App\Models\Gone', 'date_path' => 'eta', 'filters' => ['rules' => []]]);

        $this->assertSame([], $this->router()->computeWatchedColumns($unset));
        $this->assertSame([], $this->router()->computeWatchedColumns($gone));
    }

    public function test_a_stored_leaf_without_a_usable_type_is_reported_not_fatal(): void
    {
        $missing = CalendarRule::factory()->make(['filters' => ['rules' => [['data' => []]]]]);
        $nested = CalendarRule::factory()->make(['filters' => ['rules' => [['type' => ['x'], 'data' => []]]]]);
        $badGroup = CalendarRule::factory()->make(['filters' => ['rules' => [['type' => 'or', 'data' => ['groups' => [['rules' => 'zz']]]]]]]);

        $this->assertSame(['missing_type'], $this->engine()->filterProblems($missing));
        $this->assertSame(['missing_type'], $this->engine()->filterProblems($nested));
        $this->assertSame(['malformed_filters'], $this->engine()->filterProblems($badGroup));
    }

    public function test_filter_problems_are_recomputed_after_the_rule_is_edited(): void
    {
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn('pr_number')]);
        $engine = $this->engine();

        $this->assertSame([], $engine->filterProblems($rule));

        $this->travel(1)->minute();
        $rule->update(['filters' => ['rules' => [['type' => 'no_such_column', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'x']]]]]]);

        $this->assertSame(['unknown_type:no_such_column'], $engine->filterProblems($rule->fresh()));
    }

    public function test_to_many_leaf_paths_route_related_row_events(): void
    {
        $request = PurchaseRequest::factory()->create();
        $item = PurchaseRequestItem::factory()->create(['purchase_request_id' => $request->id]);
        $rule = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'items.product.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'X']]],
        ]]]);
        $rule->watched_columns = $this->router()->computeWatchedColumns($rule);
        $rule->save();

        Queue::fake();

        $this->router()->touch($item->fresh(), 'created');

        Queue::assertPushed(SyncCalendarSubject::class, fn (SyncCalendarSubject $job): bool => $job->class === PurchaseRequest::class && $job->id === $request->id);
    }

    public function test_touch_above_the_related_subject_cap_dispatches_one_rule_resync(): void
    {
        $requester = $this->userWithPermissions([]);
        collect(range(1, 51))->each(fn (): PurchaseRequest => PurchaseRequest::factory()->create(['requester_id' => $requester->id]));
        $rule = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'requester.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => $requester->name]]],
        ]]]);
        $rule->watched_columns = $this->router()->computeWatchedColumns($rule);
        $rule->save();

        Queue::fake();

        $this->router()->touch($requester, 'created');

        Queue::assertPushed(SyncCalendarRule::class, 1);
        Queue::assertNotPushed(SyncCalendarSubject::class);
    }

    public function test_routing_map_rebuilds_when_the_version_stamp_changes(): void
    {
        $needle = 'CALVR'.uniqid();
        $requestA = $this->createMatchingRequests(1, $needle)->first();
        $ruleA = CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);
        $ruleA->watched_columns = $this->router()->computeWatchedColumns($ruleA);
        $ruleA->save();

        Queue::fake();
        $this->router()->touch($requestA, 'created'); // builds the map — rule A only
        Queue::assertPushed(SyncCalendarSubject::class, 1);
        Queue::fake();

        $user = null;
        $requestB = null;
        CalendarRouter::suspended(function () use (&$user, &$requestB): void {
            $user = User::factory()->create();
            $requestB = PurchaseRequest::factory()->create(['user_id' => $user->id]);
        });
        $ruleB = CalendarRule::withoutEvents(function () use ($user): CalendarRule {
            $rule = CalendarRule::factory()->create(['filters' => ['rules' => [
                ['type' => 'creator.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => $user->name]]],
            ]]]);
            $rule->watched_columns = $this->router()->computeWatchedColumns($rule);
            $rule->saveQuietly(); // bypasses the observer's version bump — the static stays stale like another process's worker

            return $rule;
        });

        $this->router()->touch($user, 'created');
        Queue::assertNothingPushed(); // the stale map does not know rule B yet

        CalendarRouter::bumpRoutesVersion();
        $this->router()->touch($user, 'created');

        Queue::assertPushed(SyncCalendarSubject::class, fn (SyncCalendarSubject $job): bool => $job->class === PurchaseRequest::class && $job->id === $requestB->id);
    }

    public function test_sync_rule_skips_null_dates_and_applies_negative_shifts(): void
    {
        $needle = 'CALND'.uniqid();
        $dated = $this->createMatchingRequests(1, $needle)->first();
        $undated = $this->createMatchingRequests(1, $needle)->first();
        PurchaseRequest::query()->whereKey($undated->id)->update(['required_by_date' => null]);
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn($needle), 'day_shift' => -2]);

        $this->engine()->syncRule($rule);

        $hits = CalendarHit::query()->where('calendar_rule_id', $rule->id)->get();

        $this->assertEquals([$dated->id], $hits->pluck('subject_id')->all());
        $this->assertSame('2026-10-18', $hits->first()->event_date->toDateString());
    }

    public function test_sync_rule_supports_extra_paths_and_relation_date_paths(): void
    {
        $needle = 'CALEX'.uniqid();
        $request = PurchaseRequest::factory()->create(['user_id' => User::factory()]);
        PurchaseRequest::query()->whereKey($request->id)->update(['pr_number' => $needle.'-'.uniqid()]);
        $rule = CalendarRule::factory()->create([
            'filters' => $this->filterOn($needle),
            'extra_paths' => ['items.product'],
            'date_path' => 'creator.created_at',
        ]);

        $this->engine()->syncRule($rule);

        $hit = CalendarHit::query()->where('calendar_rule_id', $rule->id)->first();

        $this->assertSame($request->id, $hit->subject_id);
        $this->assertSame('2026-10-08', $hit->event_date->toDateString());
    }

    public function test_multi_chunk_sync_keeps_a_constant_query_count_per_chunk(): void
    {
        config(['calendar.sync_chunk' => 3]);
        $needleA = 'CALQA'.uniqid();
        $needleB = 'CALQB'.uniqid();
        $needleC = 'CALQC'.uniqid();
        $ruleA = CalendarRule::factory()->create(['filters' => $this->filterOn($needleA)]);
        $ruleB = CalendarRule::factory()->create(['filters' => $this->filterOn($needleB)]);
        $ruleC = CalendarRule::factory()->create(['filters' => $this->filterOn($needleC)]);
        $this->createMatchingRequests(3, $needleA);
        $this->createMatchingRequests(6, $needleB);
        $this->createMatchingRequests(9, $needleC);
        $resolver = $this->resolver();
        $resolver->constraintsForRule($ruleA);

        $engine = $this->engine($resolver);
        $connection = DB::connection();
        $connection->enableQueryLog();

        try {
            $counts = [];
            foreach ([$ruleA, $ruleB, $ruleC] as $rule) {
                $connection->flushQueryLog();
                $engine->syncRule($rule);
                $counts[] = count($connection->getQueryLog());
            }
        } finally {
            $connection->disableQueryLog();
            config(['calendar.sync_chunk' => 500]);
        }

        $this->assertSame($counts[1] - $counts[0], $counts[2] - $counts[1]);
        $this->assertGreaterThan(0, $counts[1] - $counts[0]);
        $this->assertSame(3, CalendarHit::query()->where('calendar_rule_id', $ruleA->id)->count());
        $this->assertSame(6, CalendarHit::query()->where('calendar_rule_id', $ruleB->id)->count());
        $this->assertSame(9, CalendarHit::query()->where('calendar_rule_id', $ruleC->id)->count());
    }

    public function test_send_failure_marks_nothing_and_the_retry_still_alerts_once(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $hit = $this->hitOn('2026-10-11', ['user_id' => $owner->id, 'lead_times' => [7], 'on_day' => false]);
        $rule = $hit->rule;

        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('boom'));

        try {
            $this->alerts()->sendForRule($rule, today());
            $this->fail('Expected the notification failure to propagate.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame([], $hit->fresh()->alerts_sent);

        Notification::fake();
        $this->alerts()->sendForRule($rule, today());

        Notification::assertSentTimes(CalendarAlertNotification::class, 1);
        $this->assertSame('2026-10-08', $hit->fresh()->alerts_sent[7]);
    }

    public function test_a_rearm_between_send_and_mark_wins_and_nothing_is_marked(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $hit = $this->hitOn('2026-10-11', ['user_id' => $owner->id, 'lead_times' => [7], 'on_day' => false]);
        $rule = $hit->rule;

        Notification::shouldReceive('send')->once()->andReturnUsing(function () use ($hit): void {
            CalendarHit::query()->whereKey($hit->id)->update(['event_date' => '2026-11-01']);
        });

        $this->alerts()->sendForRule($rule, today());

        $fresh = $hit->fresh();

        $this->assertSame([], $fresh->alerts_sent);
        $this->assertSame('2026-11-01', $fresh->event_date->toDateString());
        $this->assertSame(0, DB::table(config('activitylog.table_name'))
            ->where('description', 'alert_sent')
            ->where('subject_id', $hit->subject_id)
            ->count());
    }

    public function test_all_kinds_due_today_collapse_into_one_notification(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create([
            'user_id' => $owner->id,
            'type' => RuleType::ACTION->value,
            'lead_times' => [],
            'on_day' => true,
        ]);
        $onDay = CalendarHit::factory()->create(['calendar_rule_id' => $rule->id, 'event_date' => '2026-10-08']);
        $overdue = CalendarHit::factory()->create(['calendar_rule_id' => $rule->id, 'event_date' => '2026-10-01']);

        Notification::fake();

        $this->alerts()->sendForRule($rule, today());

        Notification::assertSentTimes(CalendarAlertNotification::class, 1);
        Notification::assertSentTo($owner, CalendarAlertNotification::class, function (CalendarAlertNotification $n): bool {
            return $n->entries->count() === 2
                && $n->entries->firstWhere('overdue', true) !== null
                && $n->entries->firstWhere('on_day', true) !== null;
        });

        $this->assertSame('2026-10-08', $onDay->fresh()->alerts_sent[0]);
        $this->assertSame(1, $overdue->fresh()->overdue_count);
    }

    public function test_notification_payload_shape_and_single_vs_grouped_urls(): void
    {
        $owner = $this->userWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $owner->id, 'lead_times' => [7], 'on_day' => false]);
        $single = CalendarHit::factory()
            ->forSubject($this->createMatchingRequests(1, 'CALURL'.uniqid())->first())
            ->create(['calendar_rule_id' => $rule->id, 'event_date' => '2026-10-11']);

        $payload = (new CalendarAlertNotification($rule, collect([
            ['hit' => $single, 'leads' => [7], 'on_day' => false, 'overdue' => false],
        ]), '2026-10-08'))->toDatabase($owner);

        $this->assertSame($rule->id, $payload['rule_id']);
        $this->assertSame([$single->id], $payload['hit_ids']);
        $this->assertSame('2026-10-08', $payload['alert_date']);
        $this->assertSame('resources/calendarRule/strings.alerts.title', $payload['title']['key']);
        $this->assertSame('lead', $payload['items'][0]['kind']);
        $this->assertSame(7, $payload['items'][0]['lead']);
        $this->assertSame(1, $payload['title']['params']['count']);
        $this->assertStringContainsString('/purchase-requests/'.$single->subject_id, $payload['actions'][0]['url']);

        $second = CalendarHit::factory()->create(['calendar_rule_id' => $rule->id, 'event_date' => '2026-10-12']);
        $grouped = (new CalendarAlertNotification($rule, collect([
            ['hit' => $single, 'leads' => [7], 'on_day' => false, 'overdue' => false],
            ['hit' => $second, 'leads' => [], 'on_day' => true, 'overdue' => false],
        ]), '2026-10-08'))->toDatabase($owner);

        $this->assertSame(['lead', 'on_day'], $grouped['kinds']);
        $this->assertSame(0, $grouped['body']['params']['more']);
        $this->assertStringContainsString('cal_date=', $grouped['actions'][0]['url']);
        $this->assertStringContainsString('cal_rule='.$rule->id, $grouped['actions'][0]['url']);
    }

    public function test_sync_jobs_release_their_unique_lock_when_processing_starts(): void
    {
        $rule = new SyncCalendarRule(5);
        $subject = new SyncCalendarSubject(PurchaseRequest::class, 5);

        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $rule);
        $this->assertSame('5', $rule->uniqueId());
        $this->assertSame(300, $rule->uniqueFor);
        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $subject);
        $this->assertNotInstanceOf(ShouldBeUniqueUntilProcessing::class, new SendCalendarRuleAlerts(5, '2026-10-08'));
    }

    public function test_the_rule_sync_job_is_bounded_by_time_and_caps_exceptions_not_attempts(): void
    {
        $job = new SyncCalendarRule(1);

        $this->assertFalse(property_exists($job, 'tries'));
        $this->assertSame(3, $job->maxExceptions);
        $this->assertTrue($job->retryUntil() > now()->addMinutes(20));
    }

    public function test_an_unchanged_rule_save_logs_nothing_and_dispatches_nothing(): void
    {
        $rule = CalendarRule::factory()->create();
        $logged = DB::table('activity_log')->count();

        Queue::fake();
        $rule->fresh()->save();

        Queue::assertNothingPushed();
        $this->assertSame($logged, DB::table('activity_log')->count());
    }

    public function test_alert_job_is_unique_per_rule_and_day_and_skips_stale_rules(): void
    {
        $this->assertSame('5:2026-10-08', (new SendCalendarRuleAlerts(5, '2026-10-08'))->uniqueId());
        $this->assertSame(3600, (new SendCalendarRuleAlerts(5, '2026-10-08'))->uniqueFor);

        $owner = $this->userWithPermissions(['purchase_request.view']);
        $hit = $this->hitOn('2026-10-11', ['user_id' => $owner->id, 'lead_times' => [7], 'on_day' => false]);
        $alerts = new CalendarAlerts(new CalendarActivity);

        Notification::fake();
        (new SendCalendarRuleAlerts($hit->rule->id, '2026-10-08'))->handle($alerts);
        Notification::assertSentTimes(CalendarAlertNotification::class, 1);

        $stale = $this->hitOn('2026-10-11', ['user_id' => $owner->id, 'lead_times' => [7], 'on_day' => false]);
        $stale->rule->delete();
        (new SendCalendarRuleAlerts($stale->rule->id, '2026-10-08'))->handle($alerts);
        Notification::assertSentTimes(CalendarAlertNotification::class, 1);
    }

    public function test_seen_rows_cannot_be_crowded_out_by_unrelated_read_alerts(): void
    {
        $activity = new CalendarActivity;
        $request = PurchaseRequest::factory()->create();
        $rule = CalendarRule::factory()->create();
        $activity->log('matched', $request, $rule, ['label' => 'PR-1', 'event_date' => '2026-10-20']);
        $hit = CalendarHit::factory()->forSubject($request)->create(['calendar_rule_id' => $rule->id]);
        $unrelated = PurchaseRequest::factory()->create();
        $unrelatedHit = CalendarHit::factory()->forSubject($unrelated)->create(['calendar_rule_id' => $rule->id]);

        $rows = [];
        foreach (range(1, 105) as $i) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\CalendarAlertNotification',
                'notifiable_type' => (new User)->getMorphClass(),
                'notifiable_id' => $rule->user_id,
                'data' => json_encode(['rule_id' => $rule->id, 'hit_ids' => [$unrelatedHit->id]]),
                'read_at' => now()->addMinutes($i),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        $rows[] = [
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\CalendarAlertNotification',
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => $rule->user_id,
            'data' => json_encode(['rule_id' => $rule->id, 'hit_ids' => [$hit->id]]),
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('notifications')->insert($rows);

        $this->assertContains('seen', $activity->forRecord($request, true)->pluck('event')->all());
    }

    // §13 second fix pass

    /**
     * @return array<string, mixed>
     */
    private function seededAlertRow(User $user, CalendarRule $rule, string $alertDate): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => CalendarAlertNotification::class,
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode(['rule_id' => $rule->id, 'alert_date' => $alertDate]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function test_the_stale_sweep_never_binds_the_whole_matched_set(): void
    {
        config(['calendar.sync_chunk' => 3]);
        $needle = 'CALCH'.uniqid();
        $requests = $this->createMatchingRequests(9, $needle);
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);

        try {
            $this->engine()->syncRule($rule);
            $this->assertSame(9, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());

            $requests->take(2)->each(fn (PurchaseRequest $request): bool => PurchaseRequest::query()
                ->whereKey($request->id)
                ->update(['pr_number' => 'GONE'.uniqid()]));

            Carbon::setTestNow(Carbon::parse('2026-10-09')); // §13.7: the scan's own second skips fresh hits
            $connection = DB::connection();
            $connection->enableQueryLog();
            $connection->flushQueryLog();
            $this->engine()->syncRule($rule);
            $queries = $connection->getQueryLog();
            $connection->disableQueryLog();

            $this->assertSame(7, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());

            $deletes = collect($queries)->filter(fn (array $query): bool => str_contains($query['query'], 'delete from `calendar_hits`'));

            $this->assertSame(2, $deletes->sum(fn (array $query): int => count($query['bindings'])));
            $this->assertTrue(collect($queries)->every(fn (array $query): bool => ! str_contains(strtolower($query['query']), 'not in (')));
        } finally {
            config(['calendar.sync_chunk' => 500]);
        }
    }

    public function test_sync_subject_under_an_invalid_rule_creates_no_hit_and_writes_no_activity(): void
    {
        $needle = 'CALSBJ'.uniqid();
        $request = $this->createMatchingRequests(1, $needle)->first();
        $rule = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'renamed_column', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'X']]],
        ]]]);
        $baseline = $this->ruleActivityCount($rule); // the observer already logged rule_updated on create

        $this->engine()->syncSubject(PurchaseRequest::class, $request->id);

        $this->assertSame(0, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());
        $this->assertSame($baseline, $this->ruleActivityCount($rule));
    }

    private function ruleActivityCount(CalendarRule $rule): int
    {
        return DB::table(config('activitylog.table_name'))
            ->where('log_name', 'calendar')
            ->where('subject_id', $rule->id)
            ->where('subject_type', $rule->getMorphClass())
            ->count();
    }

    public function test_consecutive_syncs_of_the_same_invalid_rule_log_one_row(): void
    {
        $request = $this->createMatchingRequests(1, 'CALRLG'.uniqid())->first();
        $rule = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'renamed_column', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'X']]],
        ]]]);

        $this->engine()->syncRule($rule);
        $this->engine()->syncRule($rule);

        $this->assertSame(1, $this->ruleInvalidCount($rule));
    }

    public function test_preview_rejects_malformed_or_blocks(): void
    {
        $permitted = $this->userWithPermissions(['purchase_request.view']);
        $engine = $this->engine();
        $malformed = [
            ['rules' => [['type' => 'or', 'data' => 'zz']]],
            ['rules' => [['type' => 'or', 'data' => ['groups' => 'zz']]]],
        ];

        foreach ($malformed as $filters) {
            try {
                $engine->preview($permitted, PurchaseRequest::class, $filters, [], 'required_by_date', 0);
                $this->fail('Malformed OR block accepted: '.json_encode($filters));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_class_reached_by_two_paths_resyncs_the_subjects_of_both(): void
    {
        $user = User::factory()->create();
        $createdByUser = PurchaseRequest::factory()->create(['user_id' => $user->id]);
        $requestedByUser = PurchaseRequest::factory()->create(['requester_id' => $user->id]);
        $rule = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'creator.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => $user->name]]],
            ['type' => 'requester.name', 'data' => ['operator' => 'contains', 'settings' => ['text' => $user->name]]],
        ]]]);
        $rule->watched_columns = $this->router()->computeWatchedColumns($rule);
        $rule->save();

        Queue::fake();

        $this->router()->touch($user, 'created');

        Queue::assertPushed(SyncCalendarSubject::class, 2);
        Queue::assertPushed(SyncCalendarSubject::class, fn (SyncCalendarSubject $job): bool => $job->id === $createdByUser->id);
        Queue::assertPushed(SyncCalendarSubject::class, fn (SyncCalendarSubject $job): bool => $job->id === $requestedByUser->id);
        Queue::assertNotPushed(SyncCalendarRule::class);
    }

    public function test_a_busy_rule_lock_defers_the_sync_instead_of_dropping_it(): void
    {
        $needle = 'CALBZ'.uniqid();
        $this->createMatchingRequests(1, $needle);
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);
        $lock = Cache::lock('calendar_rule_sync:'.$rule->id, 30);
        $engine = $this->engine();

        try {
            $this->assertTrue($lock->get());

            $this->assertFalse($engine->syncRule($rule));
            $this->assertSame(0, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());

            $lock->release();

            $this->assertTrue($engine->syncRule($rule));
            $this->assertSame(1, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());

            $lock->get();

            (new SyncCalendarRule($rule->id))->handle($engine); // busy path: no sync, no crash
            $this->assertSame(1, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());

            $lock->release();
            (new SyncCalendarRule($rule->id))->handle($engine);
        } finally {
            $lock->release();
        }

        $this->assertSame(1, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());
    }

    public function test_hits_created_during_the_scan_survive_the_sweep(): void
    {
        $needle = 'CALFS'.uniqid();
        $matching = $this->createMatchingRequests(1, $needle)->first();
        $outside = PurchaseRequest::factory()->create();
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);

        $this->engine()->syncRule($rule);

        // a hit written by syncSubject at the exact second the next scan starts
        CalendarHit::factory()->forSubject($outside)->create(['calendar_rule_id' => $rule->id, 'event_date' => '2026-10-20']);

        $this->engine()->syncRule($rule);

        $this->assertSame(2, CalendarHit::query()->where('calendar_rule_id', $rule->id)->count());

        Carbon::setTestNow(Carbon::parse('2026-10-09'));
        $this->engine()->syncRule($rule);

        $remaining = CalendarHit::query()->where('calendar_rule_id', $rule->id)->get();

        $this->assertCount(1, $remaining);
        $this->assertSame($matching->id, $remaining->first()->subject_id);
        $this->assertSame(1, $this->activityCountFor([$outside], 'cleared'));
    }

    public function test_cold_version_cache_rebuilds_the_routing_map(): void
    {
        Cache::forget('calendar_routes_version');

        $needle = 'CALCOLD'.uniqid();
        $request = $this->createMatchingRequests(1, $needle)->first();
        $rule = CalendarRule::factory()->create(['filters' => $this->filterOn($needle)]);
        $rule->watched_columns = $this->router()->computeWatchedColumns($rule);
        $rule->save();

        Queue::fake();

        $this->router()->touch($request->fresh(), 'created');

        Queue::assertPushed(SyncCalendarSubject::class, fn (SyncCalendarSubject $job): bool => $job->id === $request->id);
    }

    public function test_a_retry_after_a_partial_send_only_notifies_the_missing_recipients(): void
    {
        $first = $this->userWithPermissions(['purchase_request.view']);
        $second = $this->userWithPermissions(['purchase_request.view']);
        $third = $this->userWithPermissions(['purchase_request.view']);
        $hit = $this->hitOn('2026-10-11', [
            'user_id' => $first->id,
            'visibility' => Visibility::USERS->value,
            'shared_user_ids' => [$first->id, $second->id, $third->id],
            'lead_times' => [7],
            'on_day' => false,
        ]);
        $rule = $hit->rule;

        DB::table('notifications')->insert([
            $this->seededAlertRow($first, $rule, '2026-10-08'),
            $this->seededAlertRow($second, $rule, '2026-10-08'),
        ]);

        $sentTo = [];
        Notification::shouldReceive('send')->once()->andReturnUsing(function (User $recipient) use (&$sentTo): void {
            $sentTo[] = $recipient->id;
        });

        $this->alerts()->sendForRule($rule, today());

        $this->assertSame([$third->id], $sentTo);
        $this->assertSame('2026-10-08', $hit->fresh()->alerts_sent[7]);
    }

    public function test_a_retry_after_a_failed_commit_does_not_double_send(): void
    {
        $first = $this->userWithPermissions(['purchase_request.view']);
        $second = $this->userWithPermissions(['purchase_request.view']);
        $hit = $this->hitOn('2026-10-11', [
            'user_id' => $first->id,
            'visibility' => Visibility::USERS->value,
            'shared_user_ids' => [$first->id, $second->id],
            'lead_times' => [7],
            'on_day' => false,
        ]);
        $rule = $hit->rule;

        // the send succeeded, the commit failed: every recipient already holds the notification
        DB::table('notifications')->insert([
            $this->seededAlertRow($first, $rule, '2026-10-08'),
            $this->seededAlertRow($second, $rule, '2026-10-08'),
        ]);

        Notification::shouldReceive('send')->never();

        $this->alerts()->sendForRule($rule, today());

        $this->assertSame('2026-10-08', $hit->fresh()->alerts_sent[7]);
    }

    public function test_pruning_drops_empty_sets_and_empty_or_blocks_but_keeps_filled_trees_unchanged(): void
    {
        $tree = new CalendarFilterTree;
        $leaf = ['type' => 'pr_number', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'A']]];
        $or = fn (array $groups): array => ['type' => 'or', 'data' => ['groups' => $groups]];
        $legacy = [$leaf, $or(['ab12' => ['rules' => [$leaf]], 'cd34' => ['rules' => [$leaf, $leaf]]])];

        $this->assertSame($legacy, $tree->prune($legacy));
        $this->assertSame([], $tree->prune([$or(['a' => ['rules' => []], 'b' => ['rules' => []]])]));
        $this->assertSame([$leaf], $tree->prune([$leaf, $or(['a' => ['rules' => []], 'b' => ['rules' => []]])]));
        $this->assertSame([$or(['b' => ['rules' => [$leaf]]])], $tree->prune([$or(['a' => ['rules' => []], 'b' => ['rules' => [$leaf]]])]));
        $this->assertSame([], $tree->prune([$or(['a' => ['rules' => [$or(['x' => ['rules' => []]])]]])]));
    }

    private function constraintNamed(string $subject, string $name, array $extra = [], array $text = []): Constraint
    {
        return collect($this->resolver()->constraintsFor($subject, $extra, null, [], $text))->first(fn (Constraint $constraint): bool => $constraint->getName() === $name);
    }

    public function test_predefined_options_resolve_enum_form_stored_boolean_and_nothing(): void
    {
        PredefinedOptions::flush();
        $options = app(PredefinedOptions::class);

        $this->assertEqualsCanonicalizing(array_map(fn (RuleType $type): string => $type->value, RuleType::cases()), array_keys($options->optionsFor(CalendarRule::class, 'type')));
        $this->assertSame(['air', 'sea', 'land', 'multimodal'], array_keys($options->optionsFor(ProformaInvoice::class, 'transport_mode')));
        $this->assertSame(__('resources/proformaInvoice/strings.general.transport_modes.sea'), $options->optionsFor(ProformaInvoice::class, 'transport_mode')['sea']);
        $this->assertSame(['1' => __('resources/general/strings.yes'), '0' => __('resources/general/strings.no')], $options->optionsFor(CalendarRule::class, 'is_active'));
        $this->assertNull($options->optionsFor(PurchaseRequest::class, 'pr_number'));
        $this->assertNull($options->optionsFor(PurchaseRequest::class, 'id'));
        $this->assertNull($options->optionsFor(PurchaseRequest::class, 'requester_id'));
        $this->assertNull($options->optionsFor(Payment::class, 'iban'));
        $this->assertNull($options->optionsFor(\App\Models\Correspondence::class, 'correspondable_type'));
    }

    public function test_predefined_options_follow_the_locale_and_scan_low_cardinality_text_columns(): void
    {
        app()->setLocale('fa');
        PredefinedOptions::flush();
        $label = app(PredefinedOptions::class)->optionsFor(ProformaInvoice::class, 'transport_mode')['sea'];
        $this->assertSame(__('resources/proformaInvoice/strings.general.transport_modes.sea'), $label);
        $this->assertNotSame('Sea', $label);

        app()->setLocale('en');
        DB::table('status_histories')->update(['field' => 'zz_scan']);
        PredefinedOptions::flush();
        PredefinedOptions::flushScan('status_histories');

        $this->assertSame(['zz_scan' => 'zz_scan'], app(PredefinedOptions::class)->optionsFor(\App\Models\StatusHistory::class, 'field'));
        PredefinedOptions::flushScan('status_histories');
    }

    public function test_a_predefined_text_column_gets_the_simple_select_pair_and_legacy_text_leaves_keep_text(): void
    {
        PredefinedOptions::flush();
        $mode = $this->constraintNamed(ProformaInvoice::class, 'transport_mode');
        $urgency = $this->constraintNamed(PurchaseRequest::class, 'urgency_level');

        $this->assertInstanceOf(SelectConstraint::class, $mode);
        $this->assertTrue($mode->isMultiple());
        $this->assertSame(['is', 'isFilled'], array_keys($mode->getOperators()));
        $this->assertSame(['is'], array_keys($urgency->getOperators()));
        $this->assertSame(__('resources/proformaInvoice/strings.general.transport_modes.sea'), $mode->getOptions()['sea']);
        $this->assertInstanceOf(TextConstraint::class, $this->constraintNamed(PurchaseRequest::class, 'pr_number'));
        $this->assertInstanceOf(TextConstraint::class, $this->constraintNamed(ProformaInvoice::class, 'transport_mode', [], ['transport_mode']));
    }

    public function test_a_select_leaf_syncs_end_to_end_and_a_legacy_contains_leaf_still_validates_and_syncs(): void
    {
        $high = PurchaseRequest::factory()->create();
        $low = PurchaseRequest::factory()->create();
        PurchaseRequest::query()->whereKey($high->id)->update(['urgency_level' => 'high', 'required_by_date' => '2026-10-20']);
        PurchaseRequest::query()->whereKey($low->id)->update(['urgency_level' => 'low', 'required_by_date' => '2026-10-20']);

        $select = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'urgency_level', 'data' => ['operator' => 'is', 'settings' => ['values' => ['high']]]],
        ]]]);
        $legacy = CalendarRule::factory()->create(['filters' => ['rules' => [
            ['type' => 'urgency_level', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'hig']]],
        ]]]);

        foreach ([$select, $legacy] as $rule) {
            $this->assertSame([], $this->engine()->filterProblems($rule));
            $this->engine()->syncRule($rule);
            $this->assertSame([$high->id], CalendarHit::query()->where('calendar_rule_id', $rule->id)->whereIn('subject_id', [$high->id, $low->id])->pluck('subject_id')->all());
        }

        $this->assertInstanceOf(TextConstraint::class, collect($this->resolver()->constraintsForRule($legacy))->first());
        $this->assertInstanceOf(SelectConstraint::class, collect($this->resolver()->constraintsForRule($select))->first());
        $this->assertStringContainsString(__('resources/purchaseRequest/strings.general.urgency.high'), $this->resolver()->sentence(PurchaseRequest::class, [], $select->filters['rules']));
    }

    public function test_lookup_table_name_columns_are_listed_as_existing_value_selects_and_legacy_text_leaves_stay_text(): void
    {
        $user = $this->userWithPermissions(['purchase_request.view', 'department.view']);
        $byName = fn (array $text = []): Collection => collect($this->resolver()->constraintsFor(PurchaseRequest::class, ['costCenter'], $user, [], $text))->keyBy(fn (Constraint $constraint): string => $constraint->getName());

        $this->assertTrue($byName()->has('costCenter'));

        foreach (['name', 'english_name', 'code'] as $column) {
            $this->assertInstanceOf(SelectConstraint::class, $byName()["costCenter.{$column}"], $column);
        }

        $this->assertInstanceOf(TextConstraint::class, $byName()['costCenter.description']);
        $this->assertInstanceOf(TextConstraint::class, $byName(['costCenter.name'])['costCenter.name']);
    }

    public function test_only_allowlisted_reference_models_get_value_lists_and_operational_identifiers_stay_text(): void
    {
        $user = $this->userWithPermissions(collect($this->modelClasses())->map(fn (string $class): string => Str::snake(class_basename($class)).'.view')->all());
        $this->assertEqualsCanonicalizing([\App\Models\Bank::class, \App\Models\Category::class, \App\Models\Company::class, \App\Models\Currency::class, Department::class, Product::class, \App\Models\Status::class], PredefinedOptions::LOOKUP_MODELS);

        foreach (array_keys(CalendarModules::all()) as $subject) {
            $tables = $this->resolver()->tableOptions($subject, $user);

            foreach ([...array_keys($tables['direct']), ...array_keys($tables['far'])] as $path) {
                if ($path === CalendarPathResolver::OWN_TABLE) {
                    continue;
                }

                $hops = $this->resolver()->validateRelationPath($subject, $path);
                $class = end($hops)['class'];

                foreach ($this->resolver()->constraintsFor($subject, [$path], $user) as $constraint) {
                    if (str_starts_with($constraint->getName(), $path.'.') && $constraint instanceof SelectConstraint && $constraint->getSearchResultsUsingCallback() !== null) {
                        $this->assertContains($class, PredefinedOptions::LOOKUP_MODELS, "{$subject} {$constraint->getName()} is not a reference model");
                        $this->assertContains(Str::afterLast($constraint->getName(), '.'), NameSearch::columns(new $class));
                    }
                }
            }
        }

        foreach ([\App\Models\RegisteredOrder::class => 'ro_number', \App\Models\PurchaseOrder::class => 'po_number', Shipment::class => 'shipment_no'] as $class => $identifier) {
            $this->assertNotContains($class, PredefinedOptions::LOOKUP_MODELS);
            $this->assertNull(collect($this->resolver()->constraintsFor(PurchaseRequest::class, [], $user))->first(fn (Constraint $constraint): bool => $constraint->getName() === $identifier && $constraint->getSearchResultsUsingCallback() !== null));
        }
    }

    public function test_option_sources_follow_the_priority_order_enum_before_form_and_morph_columns_are_never_listed(): void
    {
        PredefinedOptions::flush();
        $options = app(PredefinedOptions::class);
        $visibility = $options->optionsFor(CalendarRule::class, 'visibility');

        $this->assertSame(array_map(fn (Visibility $case): string => $case->value, Visibility::cases()), array_keys($visibility));
        $this->assertSame(Visibility::from('me')->getLabel(), $visibility['me']);

        foreach ([[\App\Models\Target::class, 'targetable_type'], [\App\Models\Target::class, 'targetable_id'], [\App\Models\Payment::class, 'targetable_type'], [\App\Models\Correspondence::class, 'correspondable_type'], [\App\Models\EntityAttribute::class, 'entity_type']] as [$class, $column]) {
            $this->assertNull($options->optionsFor($class, $column), "{$class}.{$column}");
        }
    }

    private const KNOWN_CLOSED_COLUMNS = [
        'CalendarRule' => ['type', 'color', 'visibility', 'notification_type', 'on_day', 'is_active'],
        'NotificationSetting' => ['notification_type'],
        'Bank' => ['is_active'], 'Company' => ['is_active'], 'Currency' => ['is_active'], 'Department' => ['is_active'],
        'Product' => ['is_active', 'in_stock'], 'Category' => ['active', 'level'], 'Specification' => ['vat_exempt'],
        'Correspondence' => ['type', 'priority', 'is_internal', 'is_private'], 'CorrespondenceRecipient' => ['type'],
        'Custom' => ['clearance_type'], 'BankProfile' => ['supply_source'],
        'ProformaInvoice' => ['transport_mode', 'delivery_terms', 'origin_country', 'destination_country', 'beneficiary_country'],
        'ProformaInvoiceItem' => ['unit', 'origin'], 'PurchaseOrderItem' => ['unit'], 'PurchaseRequestItem' => ['unit'], 'RegisteredOrderItem' => ['unit'],
        'PurchaseOrder' => ['incoterms'], 'RegisteredOrder' => ['incoterms', 'currency_type'], 'PurchaseRequest' => ['urgency_level'],
        'Shipment' => ['container_type', 'part', 'container_no'], 'Target' => ['status', 'year', 'metrics'], 'User' => ['status', 'position'],
    ];

    private const FREE_OR_UNRESOLVABLE_COLUMNS = [
        'subject' => 'engine-owned list of model class names (backslash values), picked through the module picker',
        'date_path' => 'per-subject dynamic list of date columns',
        'shared_user_ids' => 'array of user ids, record selector',
        'notify_role_ids' => 'array of role ids, record selector',
        'shared_role_ids' => 'array of role ids, record selector',
        'notify_emails' => 'free outside e-mail list',
        'types' => 'array-valued closed set (companies.types); a scalar select cannot express array containment',
        'import_licenses' => 'array-valued closed set (specifications.import_licenses)',
        'entity_type' => 'morph class names (backslash values)',
        'targetable_type' => 'morph class names (backslash values)',
        'key' => 'user-defined free keys',
        'contract_no' => 'free-entry identifier',
        'attributes' => 'free-form tag list',
        'tags' => 'free-form tag list',
        'value' => 'virtual field of the notification value modal',
        'type' => 'statuses.type: data-derived workflow type names (scan-dependent)',
        'english_type' => 'statuses.english_type: data-derived workflow type names (scan-dependent)',
    ];

    private const ENUM_REASONS = [
        'AllowedDomain' => 'e-mail domain whitelist, no column',
        'ColumnCategory' => 'import pipeline internal state',
        'Level' => 'labels the integer categories.level (resolved from the Level filter options)',
        'Status' => 'badge helper for booleans or workflow status names stored in statuses (status_id relation)',
        'InStockStatus' => 'badge helper for a boolean column',
        'IsActiveStatus' => 'badge helper for a boolean column',
        'Type' => 'badge helper (companies.types array)',
        'UserRole' => 'role names, no column',
        'ClearanceStatus' => 'workflow status names in statuses',
        'CommitmentStatus' => 'workflow status names in statuses',
        'GuaranteeStatus' => 'workflow status names in statuses',
        'Target' => 'morph class names (backslash values)',
        'Source' => 'virtual source_type radio, no column',
    ];

    private function modelClasses(): array
    {
        return collect(glob(app_path('Models/*.php')))
            ->map(fn (string $file): string => 'App\\Models\\'.basename($file, '.php'))
            ->filter(fn (string $class): bool => is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class))
            ->values()->all();
    }

    public function test_every_known_closed_set_column_resolves_with_translated_labels_in_all_locales(): void
    {
        $resolved = 0;

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            PredefinedOptions::flush();

            foreach (self::KNOWN_CLOSED_COLUMNS as $model => $columns) {
                foreach ($columns as $column) {
                    $options = app(PredefinedOptions::class)->optionsFor('App\\Models\\'.$model, $column);
                    $this->assertNotEmpty($options, "{$locale} {$model}.{$column}");
                    $this->assertNotContains(true, array_map(fn ($label): bool => str_contains((string) $label, 'strings.'), $options), "{$locale} {$model}.{$column} raw key");
                    $resolved++;
                }
            }
        }

        $this->assertGreaterThanOrEqual(3 * 40, $resolved);
        app()->setLocale('en');
        PredefinedOptions::flush();
    }

    public function test_every_enum_cast_and_boolean_column_of_every_model_resolves(): void
    {
        PredefinedOptions::flush();
        $count = 0;

        foreach ($this->modelClasses() as $class) {
            $model = new $class;
            $columns = collect(\Illuminate\Support\Facades\Schema::getColumns($model->getTable()))->keyBy('name');

            foreach ($model->getCasts() as $column => $cast) {
                if (is_string($cast) && enum_exists($cast) && $columns->has($column)) {
                    $this->assertSame(array_map(fn ($case): string => (string) $case->value, $cast::cases()), array_keys(app(PredefinedOptions::class)->optionsFor($class, $column) ?? []), "{$class}.{$column}");
                    $count++;
                }
            }

            foreach ($columns as $column => $meta) {
                if (($meta['type'] === 'tinyint(1)' || $meta['type_name'] === 'boolean') && ! str_ends_with($column, '_id')) {
                    $this->assertSame(['1', '0'], array_map('strval', array_keys(app(PredefinedOptions::class)->optionsFor($class, $column) ?? [])), "{$class}.{$column}");
                    $count++;
                }
            }
        }

        $this->assertGreaterThanOrEqual(15, $count);
    }

    public function test_every_filament_select_radio_toggle_and_filter_naming_a_column_is_resolved_or_allowlisted(): void
    {
        PredefinedOptions::flush();
        $columns = [];

        foreach ($this->modelClasses() as $class) {
            foreach (\Illuminate\Support\Facades\Schema::getColumnListing((new $class)->getTable()) as $column) {
                $columns[$column][] = $class;
            }
        }

        $found = $covered = $allowed = 0;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Filament'))) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/(?:Select|Radio|ToggleButtons|CheckboxList|SelectFilter|TernaryFilter)::make\(\s*\'([a-z_]+)\'/', (string) file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $name) {
                if (str_ends_with($name, '_id') || ! isset($columns[$name])) {
                    continue;
                }

                $found++;

                if (collect($columns[$name])->contains(fn (string $class): bool => app(PredefinedOptions::class)->optionsFor($class, $name) !== null)) {
                    $covered++;
                } else {
                    $this->assertArrayHasKey($name, self::FREE_OR_UNRESOLVABLE_COLUMNS, "{$name} in {$file->getFilename()} names a column with a closed set that PredefinedOptions misses");
                    $allowed++;
                }
            }
        }

        $this->assertGreaterThan(60, $found);
        $this->assertSame($found, $covered + $allowed);
        $this->assertGreaterThan($allowed, $covered);
    }

    public function test_every_enum_class_is_cast_mapped_to_options_or_has_a_written_reason(): void
    {
        PredefinedOptions::flush();
        $casted = [];

        foreach ($this->modelClasses() as $class) {
            foreach ((new $class)->getCasts() as $cast) {
                if (is_string($cast) && enum_exists($cast)) {
                    $casted[$cast] = true;
                }
            }
        }

        $mapped = [
            \App\Filament\Resources\Operational\CorrespondenceResource\Enums\Priority::class => [\App\Models\Correspondence::class, 'priority'],
            \App\Filament\Resources\Operational\CorrespondenceResource\Enums\Type::class => [\App\Models\Correspondence::class, 'type'],
            \App\Filament\Resources\Master\UserResource\Enums\PositionStatus::class => [User::class, 'position'],
            \App\Filament\Resources\Master\UserResource\Enums\UserStatus::class => [User::class, 'status'],
            \App\Filament\Resources\Operational\TargetResource\Enums\Status::class => [\App\Models\Target::class, 'status'],
        ];

        foreach ($mapped as $enum => [$model, $column]) {
            $this->assertEqualsCanonicalizing(array_map(fn ($case): string => (string) $case->value, $enum::cases()), array_keys(app(PredefinedOptions::class)->optionsFor($model, $column) ?? []), $enum);
        }

        $enums = 0;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $file) {
            if ($file->getExtension() !== 'php' || ! preg_match('/^enum\s+(\w+)/m', $source = (string) file_get_contents($file->getPathname()), $name) || ! preg_match('/^namespace\s+([^;]+);/m', $source, $namespace)) {
                continue;
            }

            $enums++;
            $class = $namespace[1].'\\'.$name[1];
            $this->assertTrue(isset($casted[$class]) || isset($mapped[$class]) || isset(self::ENUM_REASONS[$name[1]]), "{$class} is neither cast, mapped nor explained");
        }

        $this->assertGreaterThanOrEqual(25, $enums);
    }

    public function test_option_labels_exist_in_fa_and_fr_without_english_fallback(): void
    {
        $acronym = '/^[A-Z0-9][A-Z0-9 \-\.\/]*$|^\d|^Cc$|^[a-z]{1,3}$/';
        $english = [];
        $same = [];

        app()->setLocale('en');
        PredefinedOptions::flush();

        foreach (self::KNOWN_CLOSED_COLUMNS as $model => $columns) {
            foreach ($columns as $column) {
                $english[$model.'.'.$column] = app(PredefinedOptions::class)->optionsFor('App\\Models\\'.$model, $column);
            }
        }

        foreach (['fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            PredefinedOptions::flush();

            foreach ($english as $key => $labels) {
                [$model, $column] = explode('.', $key);
                $localized = app(PredefinedOptions::class)->optionsFor('App\\Models\\'.$model, $column);
                $this->assertEqualsCanonicalizing(array_map('strval', array_keys($labels)), array_map('strval', array_keys($localized)), "{$locale} {$key} keys");

                foreach ($labels as $value => $label) {
                    $cognate = $locale === 'fr' && (in_array($column, ['origin_country', 'destination_country', 'beneficiary_country', 'origin'], true) || $value === 'gal'
                        || in_array("{$key}.{$value}", ['CalendarRule.type.action', 'CalendarRule.color.rose', 'CalendarRule.color.violet', 'CalendarRule.color.orange', 'Correspondence.type.note', 'ProformaInvoice.transport_mode.multimodal', 'User.position.jnr', 'User.position.snr'], true));

                    if (! $cognate && ! preg_match($acronym, $label) && ! in_array($column, ['year', 'part', 'container_no'], true) && $label === $localized[$value]) {
                        $same[] = "{$locale} {$key}.{$value}";
                    }
                }
            }
        }

        $this->assertSame([], $same);

        app()->setLocale('en');
        PredefinedOptions::flush();
    }

    public function test_a_two_name_lookup_labels_both_names_in_the_locale_order_and_a_single_name_model_is_unchanged(): void
    {
        $department = Department::factory()->create(['name' => 'بخش آزمایشی', 'english_name' => 'Probe Dept', 'code' => 'PRB-88']);
        $same = Department::factory()->create(['name' => 'Same Name', 'english_name' => 'same name', 'code' => 'PRB-89']);
        $user = $this->userWithPermissions(['purchase_request.view', 'department.view']);

        foreach (['en' => 'Probe Dept — بخش آزمایشی', 'fa' => 'بخش آزمایشی — Probe Dept'] as $locale => $expected) {
            app()->setLocale($locale);
            $this->assertSame($expected, NameSearch::label($department->fresh()), $locale);
            $constraint = collect($this->resolver()->constraintsFor(PurchaseRequest::class, [], $user))->first(fn (Constraint $constraint): bool => $constraint->getName() === 'costCenter');
            $field = $constraint->getOperator('isRelatedTo')->constraint($constraint)->getFormSchema()[0];

            foreach (['Probe', 'آزمایشی', 'PRB-88'] as $term) {
                $this->assertSame($expected, $field->getSearchResults($term)[$department->id], "{$locale} {$term}");
            }
        }

        $this->assertSame('Same Name', NameSearch::label($same->fresh()), 'Identical names are not repeated.');
        $role = Role::query()->first() ?? Role::create(['name' => 'probe_solo_role', 'guard_name' => 'web']);
        $this->assertSame($role->name, NameSearch::label($role));
        app()->setLocale('en');
    }

    public function test_every_text_column_with_a_closed_set_is_a_select_in_all_modules_and_their_two_hop_tables(): void
    {
        PredefinedOptions::flush();
        $resolver = $this->resolver();
        $user = $this->userWithPermissions(collect($this->modelClasses())->map(fn (string $class): string => Str::snake(class_basename($class)).'.view')->all());
        $selects = $checked = 0;

        foreach (array_keys(CalendarModules::all()) as $subject) {
            $tables = $resolver->tableOptions($subject, $user);
            $paths = [...array_keys($tables['direct']), ...array_keys($tables['far'])];
            $paths = array_values(array_filter($paths, fn (string $path): bool => $path !== CalendarPathResolver::OWN_TABLE));

            foreach ($resolver->constraintsFor($subject, $paths, $user) as $constraint) {
                $name = $constraint->getName();

                if (! $constraint instanceof TextConstraint && ! $constraint instanceof SelectConstraint) {
                    continue;
                }

                $hops = $resolver->validatePath($subject, $name);
                $class = $hops === [] ? $subject : end($hops)['class'];
                $column = Str::afterLast($name, '.');
                $closed = app(PredefinedOptions::class)->optionsFor($class, $column) !== null
                    || ($hops !== [] && in_array($class, PredefinedOptions::LOOKUP_MODELS, true) && in_array($column, NameSearch::columns(new $class), true) && $resolver->isLookupTarget($subject, $name === $column ? $column : Str::beforeLast($name, '.')));
                $checked++;

                $this->assertSame($closed, $constraint instanceof SelectConstraint, "{$subject} {$name}");
                $selects += $closed ? 1 : 0;
            }
        }

        $this->assertGreaterThan(20, $selects);
        $this->assertGreaterThan($selects, $checked);
    }

    public function test_every_non_technical_column_of_every_chosen_table_is_offered_in_all_modules(): void
    {
        $user = $this->userWithPermissions(collect($this->modelClasses())->map(fn (string $class): string => Str::snake(class_basename($class)).'.view')->all());
        $listed = $columnsChecked = 0;

        foreach (array_keys(CalendarModules::all()) as $subject) {
            $tables = $this->resolver()->tableOptions($subject, $user);

            foreach ([...array_keys($tables['direct']), ...array_keys($tables['far'])] as $path) {
                if ($path === CalendarPathResolver::OWN_TABLE) {
                    continue;
                }

                $hops = $this->resolver()->validateRelationPath($subject, $path);
                $names = array_map(fn (Constraint $constraint): string => $constraint->getName(), $this->resolver()->constraintsFor($subject, [$path], $user));

                foreach (array_keys($this->resolver()->columns(end($hops)['class'])) as $column) {
                    if ($column === 'id' || $column === 'path' || str_ends_with($column, '_id') || str_ends_with($column, '_type')) {
                        continue;
                    }

                    $columnsChecked++;
                    $listed += in_array("{$path}.{$column}", $names, true) ? 1 : 0;
                }
            }
        }

        $this->assertGreaterThan(300, $columnsChecked);
        $this->assertSame($columnsChecked, $listed, 'Every non-technical column of every chosen table is offered.');
    }

    public function test_every_table_reached_by_more_than_one_relation_lists_each_relation_in_all_modules(): void
    {
        $user = $this->userWithPermissions(collect($this->modelClasses())->map(fn (string $class): string => Str::snake(class_basename($class)).'.view')->all());
        $multi = [];

        foreach (array_keys(CalendarModules::all()) as $subject) {
            $tables = $this->resolver()->tableOptions($subject, $user);

            foreach (['direct', 'far'] as $group) {
                $byClass = [];

                foreach (array_keys($tables[$group]) as $path) {
                    if ($path !== CalendarPathResolver::OWN_TABLE) {
                        $hops = $this->resolver()->validateRelationPath($subject, $path);
                        $byClass[end($hops)['class']][] = $path;
                    }
                }

                foreach ($byClass as $class => $paths) {
                    if (count($paths) > 1) {
                        $multi[] = class_basename($subject).' > '.class_basename($class).': '.implode(', ', $paths);
                        $this->assertCount(count($paths), array_unique(array_map(fn (string $path): string => $tables[$group][$path], $paths)), "{$subject} {$class} labels are distinct");
                    }
                }
            }
        }

        $this->assertContains('PurchaseRequest > Department: costCenter, department', array_map(fn (string $line): string => implode(', ', [Str::before($line, ': ').': '.collect(explode(', ', Str::after($line, ': ')))->sort()->implode(', ')]), $multi));
    }
}
