<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Master\NotificationSettingResource\Pages\ManageNotificationSettings;
use App\Filament\Resources\NotificationSettingResource;
use App\Models\Currency;
use App\Models\Department;
use App\Models\NotificationSetting;
use App\Models\ProformaInvoice;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Feature\Notifications\NotificationTestCase;

class NotificationSettingFormTest extends NotificationTestCase
{
    private function actAsViewer(array $permissions = ['purchase_request.view']): User
    {
        $user = $this->recipient([], $permissions);
        $this->actingAs($user);

        return $user;
    }

    private function createData(array $settings = [], array $overrides = []): array
    {
        return array_merge([
            'notification_type' => 'in_app',
            'settings' => array_merge([
                'tables' => ['purchase_requests'],
                'actions' => ['update'],
                'columns' => ['urgency_level'],
                'values' => ['urgency_level' => ['high']],
                'users' => [$this->actor->id],
                'is_active' => true,
            ], $settings),
        ], $overrides);
    }

    private function create(array $data)
    {
        return Livewire::test(ManageNotificationSettings::class)->callAction('create', data: $data);
    }

    public function test_the_table_picker_lists_only_modules_the_user_can_view(): void
    {
        $this->actAsViewer(['purchase_request.view']);
        $this->assertSame(['purchase_requests'], array_keys(NotificationSetting::getViewableModels()));

        $this->actAsViewer(['purchase_request.view', 'payment.view', 'correspondence.view']);
        $this->assertEqualsCanonicalizing(['purchase_requests', 'payments', 'correspondences'], array_keys(NotificationSetting::getViewableModels()));

        $this->actAsViewer([]);
        $this->assertSame([], NotificationSetting::getViewableModels());
    }

    public function test_the_column_picker_ignores_tables_the_user_cannot_view_and_hostile_input(): void
    {
        $this->actAsViewer(['purchase_request.view']);

        $columns = NotificationSetting::getColumnsForSelectedTables(['purchase_requests', 'payments', 'users', 'password_reset_tokens']);
        $groups = array_values(array_map(fn ($group) => array_keys($group), $columns));

        $this->assertCount(1, $columns, 'Only the viewable module is listed.');
        $this->assertContains('urgency_level', $groups[0]);
        $this->assertSame([], NotificationSetting::getColumnsForSelectedTables('purchase_requests'));
        $this->assertSame([], NotificationSetting::getColumnsForSelectedTables(null));
        $this->assertSame([], NotificationSetting::getColumnsForSelectedTables([['purchase_requests'], 5]));
    }

    public function test_the_value_picker_lists_values_of_viewable_tables_and_never_sensitive_columns(): void
    {
        $this->request(['urgency_level' => 'high']);
        $this->actAsViewer(['purchase_request.view', 'payment.view']);

        $this->assertArrayHasKey('high', NotificationSetting::getColumnValueOptions(['purchase_requests'], 'urgency_level'));

        foreach (['iban', 'account_no', 'swift', 'buyer_comm_card_num', 'password', 'remember_token', 'api_token', 'body'] as $column) {
            $this->assertTrue(NotificationSetting::isSensitiveColumn($column));
            $this->assertSame([], NotificationSetting::getColumnValueOptions(['payments', 'proforma_invoices'], $column), "{$column} must not be listed.");
        }

        $this->actAsViewer([]);
        $this->assertSame([], NotificationSetting::getColumnValueOptions(['purchase_requests'], 'urgency_level'), 'No view access, no values.');
        $this->assertSame([], NotificationSetting::getColumnValueOptions('purchase_requests', 'urgency_level'));
    }

    public function test_id_columns_show_the_linked_records_name_even_when_the_relation_name_differs(): void
    {
        $owner = User::factory()->create(['name' => 'Name Probe Owner']);
        $this->actingAs($owner);
        $this->request(['urgency_level' => 'high']);
        $this->actAsViewer(['purchase_request.view', 'shipment.view']);

        $options = NotificationSetting::getColumnValueOptions(['purchase_requests'], 'user_id');

        $this->assertSame('Name Probe Owner', $options[(string) $owner->id] ?? null);
        $this->assertNotContains((string) $owner->id, $options, 'A resolvable id must never show as a bare number.');
    }

    public function test_the_value_picker_is_bounded(): void
    {
        $this->actAsViewer();

        $this->assertLessThanOrEqual(NotificationSetting::VALUE_OPTIONS_LIMIT, count(NotificationSetting::getColumnValueOptions(['purchase_requests'], 'pr_number')));
    }

    public function test_foreign_key_search_lists_unused_records_by_name_and_renders_selected_ids_as_names(): void
    {
        $unused = User::factory()->create(['name' => 'Zed Unused Probe']);
        $this->request(['urgency_level' => 'high']);
        $this->actAsViewer();

        $this->assertTrue(NotificationSetting::isForeignKeyColumn(['purchase_requests'], 'user_id'));
        $this->assertFalse(NotificationSetting::isForeignKeyColumn(['purchase_requests'], 'urgency_level'));
        $this->assertFalse(NotificationSetting::isForeignKeyColumn(['purchase_requests'], 'password_id'));
        $this->assertArrayNotHasKey((string) $unused->id, NotificationSetting::getColumnValueOptions(['purchase_requests'], 'user_id'));

        $found = NotificationSetting::searchForeignKeyOptions(['purchase_requests'], 'user_id', 'Zed Unused');
        $this->assertSame('Zed Unused Probe', $found[$unused->id] ?? null);
        $this->assertLessThanOrEqual(NotificationSetting::FOREIGN_SEARCH_LIMIT, count(NotificationSetting::searchForeignKeyOptions(['purchase_requests'], 'user_id', '')));
        $this->assertSame([], NotificationSetting::searchForeignKeyOptions(['purchase_requests'], 'user_id', 'no-such-name-%_'));
        $this->assertSame('Zed Unused Probe', NotificationSetting::foreignKeyLabels(['purchase_requests'], 'user_id', [$unused->id])[$unused->id] ?? null);
        $this->assertSame([], NotificationSetting::searchForeignKeyOptions(['payments'], 'user_id', 'Zed'), 'No view access, no records.');

        $this->create($this->createData(['columns' => ['user_id'], 'values' => ['user_id' => [(string) $unused->id]]]))->assertHasNoActionErrors();
        $this->assertEquals(['user_id' => [$unused->id]], NotificationSetting::latest('id')->first()->getValues());
    }

    public function test_foreign_key_search_matches_every_name_column_in_both_languages_and_labels_by_locale(): void
    {
        $department = Department::factory()->create(['name' => 'بخش مالی پژوهشی', 'english_name' => 'Probe Finance', 'code' => 'PRB-77']);
        $this->actAsViewer();

        foreach (['Probe Fin', 'مالی پژو', 'PRB-77', (string) $department->id] as $term) {
            $this->assertArrayHasKey($department->id, NotificationSetting::searchForeignKeyOptions(['purchase_requests'], 'department_id', $term), $term);
        }

        app()->setLocale('en');
        $this->assertSame('Probe Finance — بخش مالی پژوهشی', NotificationSetting::searchForeignKeyOptions(['purchase_requests'], 'department_id', 'مالی پژو')[$department->id]);
        $this->assertSame('Probe Finance — بخش مالی پژوهشی', NotificationSetting::foreignKeyLabels(['purchase_requests'], 'department_id', [$department->id])[$department->id]);
        app()->setLocale('fa');
        $this->assertSame('بخش مالی پژوهشی — Probe Finance', NotificationSetting::searchForeignKeyOptions(['purchase_requests'], 'department_id', 'Probe Fin')[$department->id]);
        $this->assertSame('بخش مالی پژوهشی — Probe Finance', NotificationSetting::foreignKeyLabels(['purchase_requests'], 'department_id', [$department->id])[$department->id]);
        $this->assertSame([], NotificationSetting::searchForeignKeyOptions(['purchase_requests'], 'department_id', "Probe%' OR 1=1 --_\\"));
    }

    public function test_foreign_key_columns_get_a_name_select_plus_that_stores_the_int_id_and_fires(): void
    {
        $viewer = $this->actAsViewer(['purchase_request.view', 'proforma_invoice.view']);
        $currency = Currency::factory()->create(['name' => 'ارز آزمایشی', 'english_name' => 'Probe Coin']);
        $other = Currency::factory()->create();
        $tables = ['proforma_invoices'];

        $selector = NotificationSettingResource::getForeignKeyValueSelector('main_currency_id');
        $this->assertTrue($selector->hasCreateOptionActionFormSchema());
        $field = NotificationSettingResource::getNewForeignKeyField($tables, 'main_currency_id');
        $this->assertInstanceOf(Select::class, $field);
        $this->assertArrayHasKey($currency->id, NotificationSetting::searchForeignKeyOptions($tables, 'main_currency_id', 'ارز آزمایشی'));
        $this->assertArrayHasKey($currency->id, NotificationSetting::searchForeignKeyOptions($tables, 'main_currency_id', 'Probe Coin'));

        $created = ($selector->getCreateOptionUsing())(['value' => (string) $currency->id], fn () => $tables);
        $this->assertSame($currency->id, $created);
        $this->assertNull(NotificationSetting::foreignKeyId($tables, 'main_currency_id', 'Probe Coin'));
        $this->assertNull(NotificationSetting::foreignKeyId($tables, 'main_currency_id', 99999999));

        $this->rule($viewer, ['tables' => $tables, 'columns' => ['main_currency_id'], 'values' => ['main_currency_id' => [$created]]]);
        $invoice = ProformaInvoice::factory()->create(['main_currency_id' => $other->id]);
        $before = $this->sentCount($viewer);
        $invoice->update(['main_currency_id' => $currency->id]);
        $this->assertSame($before + 1, $this->sentCount($viewer));
    }

    public function test_id_columns_never_store_text_or_missing_ids_and_unresolved_ones_are_numeric_only(): void
    {
        $this->actAsViewer(['purchase_request.view', 'proforma_invoice.view', 'bank_profile.view']);
        $currency = Currency::factory()->create();
        $clean = NotificationSetting::sanitizeSettings([
            'tables' => ['proforma_invoices'], 'actions' => ['update'], 'columns' => ['main_currency_id'],
            'values' => ['main_currency_id' => [(string) $currency->id, 'Probe Coin', '-3', '1.5', 99999999]],
        ]);

        $this->assertSame([(string) $currency->id], $clean['values']['main_currency_id']);
        $this->assertTrue(NotificationSetting::isForeignKeyColumn(['bank_profiles'], 'currency_id'));
        $this->assertTrue(NotificationSetting::isValidTypedValue(['bank_profiles'], 'currency_id', '12'));

        foreach (['abc', '-1', '1.5', '1e3'] as $bad) {
            $this->assertFalse(NotificationSetting::isValidTypedValue(['bank_profiles'], 'currency_id', $bad), $bad);
        }

        $input = NotificationSettingResource::getNewValueField(['bank_profiles'], 'currency_id');
        $this->assertInstanceOf(TextInput::class, $input);
        $this->assertTrue($input->isNumeric());
    }

    public function test_a_typed_new_value_is_accepted_and_stored(): void
    {
        $this->actAsViewer();

        $this->create($this->createData(['columns' => ['pr_number'], 'values' => ['pr_number' => ['  brand-new-level  ', 'high']]]))->assertHasNoActionErrors();

        $this->assertSame(['pr_number' => ['brand-new-level', 'high']], NotificationSetting::latest('id')->first()->getValues());
    }

    public function test_sanitizing_trims_caps_and_drops_hostile_typed_values(): void
    {
        $this->actAsViewer();

        $clean = NotificationSetting::sanitizeSettings([
            'tables' => ['purchase_requests'],
            'actions' => ['update'],
            'columns' => ['pr_number'],
            'values' => ['pr_number' => [' a ', '', 'a', str_repeat('x', 256), ['n'], new \stdClass, ...range(1, 60)]],
        ]);

        $this->assertSame('a', $clean['values']['pr_number'][0]);
        $this->assertCount(NotificationSetting::VALUES_PER_COLUMN_LIMIT, $clean['values']['pr_number']);
        $this->assertNotContains('', $clean['values']['pr_number']);
    }

    public function test_polymorphic_id_columns_are_hidden_everywhere_while_normal_ids_stay(): void
    {
        $this->actAsViewer(['correspondence.view']);

        $listed = array_keys(array_merge(...array_values(NotificationSetting::getColumnsForSelectedTables(['correspondences']))));
        $this->assertNotContains('correspondable_id', $listed);
        $this->assertContains('correspondable_type', $listed);
        $this->assertContains('user_id', $listed);
        $this->assertTrue(NotificationSetting::isMorphIdColumn('correspondences', 'correspondable_id'));
        $this->assertFalse(NotificationSetting::isMorphIdColumn('correspondences', 'user_id'));
        $this->assertSame([], NotificationSetting::getColumnValueOptions(['correspondences'], 'correspondable_id'));

        $clean = NotificationSetting::sanitizeSettings([
            'tables' => ['correspondences'],
            'actions' => ['update'],
            'columns' => ['correspondable_id', 'user_id'],
            'values' => ['correspondable_id' => ['5'], 'user_id' => [(string) $this->actor->id]],
        ]);

        $this->assertSame(['user_id'], $clean['columns']);
        $this->assertSame(['user_id'], array_keys($clean['values']));
    }

    public function test_typing_in_the_value_picker_searches_the_database_beyond_the_default_list(): void
    {
        $this->actAsViewer();
        $limit = NotificationSetting::VALUE_OPTIONS_LIMIT;
        $base = $this->request(['notes' => 'aaa-000']);
        $rows = [];

        for ($i = 1; $i <= $limit + 5; $i++) {
            $rows[] = array_merge($base->getAttributes(), ['id' => null, 'pr_number' => 'ZZ-'.$i, 'notes' => sprintf('aaa-%03d', $i)]);
        }

        \Illuminate\Support\Facades\DB::table('purchase_requests')->insert($rows);
        $late = sprintf('aaa-%03d', $limit + 5);

        $this->assertCount($limit, NotificationSetting::getColumnValueOptions(['purchase_requests'], 'notes'));
        $this->assertArrayNotHasKey($late, NotificationSetting::getColumnValueOptions(['purchase_requests'], 'notes'));
        $this->assertSame([$late], array_keys(NotificationSetting::valueChoices(['purchase_requests'], 'notes', $late)));
        $this->assertLessThanOrEqual(NotificationSetting::VALUES_PER_COLUMN_LIMIT, count(NotificationSetting::valueChoices(['purchase_requests'], 'notes', 'aaa')));
        $this->assertSame([], NotificationSetting::valueChoices(['purchase_requests'], 'notes', "%' OR '1'='1"));
        $this->assertSame([], NotificationSetting::valueChoices(['purchase_requests'], 'notes', '%'));
        $this->assertSame([], NotificationSetting::valueChoices(['purchase_requests'], 'notes', '_'));
        $this->assertSame([], NotificationSetting::valueChoices(['purchase_requests'], 'password', 'a'));

        $this->actAsViewer([]);
        $this->assertSame([], NotificationSetting::valueChoices(['purchase_requests'], 'notes', 'aaa'));
    }

    public function test_column_options_use_the_module_localized_label_while_keys_stay_raw(): void
    {
        $this->actAsViewer();

        $label = fn () => collect(NotificationSetting::getColumnsForSelectedTables(['purchase_requests']))->first()['required_by_date'] ?? null;
        $en = $label();
        app()->setLocale('fa');
        $fa = $label();
        app()->setLocale('en');

        $this->assertNotNull($en);
        $this->assertNotSame($en, $fa, 'fa renders the module\'s own localized name.');
        $this->assertSame($en, NotificationSetting::columnLabel(['purchase_requests'], 'required_by_date'));

        $this->create($this->createData(['columns' => ['notes'], 'values' => []]))->assertHasNoActionErrors();
        $this->assertSame(['notes'], NotificationSetting::latest('id')->first()->getColumns());
    }

    public function test_every_selectable_column_label_of_every_module_is_localized_in_fa_and_fr(): void
    {
        $shared = ['ETA', 'ETD', 'IBAN', 'SWIFT', 'BIC', 'BL', 'ID', 'HS'];
        $cognates = ['Notes', 'Type', 'Incoterms'];
        $models = NotificationSetting::scannableModels();
        $untranslated = [];

        $this->assertCount(9, $models);

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);

            foreach ($models as $table => $class) {
                foreach (NotificationSetting::selectableColumns($table) as $column) {
                    $label = NotificationSetting::columnLabel([$table], $column);
                    $headline = \Illuminate\Support\Str::headline(\Illuminate\Support\Str::replaceEnd('_id', '', $column));
                    $fallback = $label === $headline && ! in_array($label, [...$shared, ...$cognates], true);
                    $latin = $locale === 'fa' && preg_match('/[A-Za-z]/', preg_replace('/\b('.implode('|', $shared).')\b/', '', $label));

                    ($locale !== 'en' && ($fallback || $latin)) && $untranslated[] = "{$locale} {$table}.{$column}";
                    $this->assertDoesNotMatchRegularExpression('/[_]|[a-z][A-Z]/', $label, "{$locale} {$table}.{$column}");
                }
            }
        }

        app()->setLocale('en');
        $this->assertSame([], $untranslated);

        foreach (['columns', 'relations'] as $group) {
            $keys = fn (string $locale): array => array_keys(trans("resources/general/strings.{$group}", [], $locale));
            $this->assertEqualsCanonicalizing($keys('en'), $keys('fa'));
            $this->assertEqualsCanonicalizing($keys('en'), $keys('fr'));
        }
    }

    public function test_a_text_column_lists_its_existing_values_visibly_and_the_create_field_matches_the_column_type(): void
    {
        $this->request(['urgency_level' => 'high']);
        $this->actAsViewer();

        $this->assertArrayHasKey('high', NotificationSetting::valueChoices(['purchase_requests'], 'urgency_level'));
        $this->assertSame(['high'], array_keys(NotificationSetting::valueChoices(['purchase_requests'], 'urgency_level', 'HIG')));
        $this->assertSame([], NotificationSetting::valueChoices(['purchase_requests'], 'urgency_level', 'zzz-none'));

        $field = fn (string $table, string $column) => get_class(NotificationSettingResource::getNewValueField([$table], $column));
        $this->assertSame(TextInput::class, $field('purchase_requests', 'urgency_level'));
        $this->assertSame(TextInput::class, $field('purchase_requests', 'total_estimated_cost'));
        $this->assertSame(DatePicker::class, $field('purchase_requests', 'required_by_date'));
        $this->assertSame(DatePicker::class, $field('purchase_requests', 'approval_date'));
        $this->assertSame(Select::class, $field('correspondences', 'is_internal'));
        $this->assertSame(['1', '0'], array_map('strval', array_keys(NotificationSetting::valueChoices(['correspondences'], 'is_internal'))));
        $this->assertSame('Yes', NotificationSetting::valueChoices(['correspondences'], 'is_internal')['1']);
    }

    public function test_typed_values_must_fit_the_column_type_and_valid_ones_are_stored(): void
    {
        $this->actAsViewer();
        $typed = fn (string $column, array $values) => $this->createData(['columns' => [$column], 'values' => [$column => $values]]);

        foreach ([
            'number' => $typed('total_estimated_cost', ['abc']),
            'date' => $typed('required_by_date', ['2026-13-45']),
            'datetime' => $typed('approval_date', ['tomorrow-ish']),
        ] as $label => $data) {
            $before = NotificationSetting::count();
            $this->create($data)->assertHasActionErrors();
            $this->assertSame($before, NotificationSetting::count(), $label.' must store nothing.');
        }

        $this->create($typed('total_estimated_cost', ['150.5']))->assertHasNoActionErrors();
        $this->create($typed('required_by_date', ['2026-10-15']))->assertHasNoActionErrors();
        $this->assertSame(['required_by_date' => ['2026-10-15']], NotificationSetting::latest('id')->first()->getValues());

        $this->assertTrue(NotificationSetting::isValidTypedValue(['correspondences'], 'is_internal', '1'));
        $this->assertFalse(NotificationSetting::isValidTypedValue(['correspondences'], 'is_internal', 'maybe'));
        $this->assertSame('2026-10-05', NotificationSetting::canonicalTypedValue(['purchase_requests'], 'required_by_date', '2026-10-05'));
        $this->assertNull(NotificationSetting::canonicalTypedValue(['purchase_requests'], 'total_estimated_cost', '1e5'));
    }

    public function test_date_values_are_labelled_by_the_calendar_setting_while_keys_stay_canonical(): void
    {
        $this->request(['required_by_date' => '2026-10-15', 'approval_date' => '2026-10-16 08:00:00']);
        $this->request(['required_by_date' => '2026-10-15', 'approval_date' => '2026-10-16 17:30:00']);
        $this->actAsViewer();
        $rule = new NotificationSetting(['settings' => ['tables' => ['purchase_requests'], 'columns' => ['required_by_date', 'approval_date'], 'values' => ['required_by_date' => ['2026-10-15'], 'approval_date' => ['2026-10-16 08:00:00']]]]);

        foreach ([['gregorian', toGregorianDate('2026-10-15')], ['jalali', toPersianDate('2026-10-15')]] as [$calendar, $expected]) {
            session(['calendar_type' => $calendar]);
            $options = NotificationSetting::getColumnValueOptions(['purchase_requests'], 'required_by_date');

            $this->assertSame($expected, $options['2026-10-15'], $calendar);
            $this->assertSame($expected, NotificationSetting::typedValueLabels(['purchase_requests'], 'required_by_date', ['2026-10-15'])['2026-10-15']);
            $this->assertStringContainsString($expected, $rule->getValueLabels()[0]);
        }

        app()->setLocale('fa');
        $this->assertStringStartsWith(NotificationSetting::columnLabel(['purchase_requests'], 'required_by_date').': ', $rule->getValueLabels()[0], 'Summaries use the localized column label, not the raw key.');
        app()->setLocale('en');

        $days = NotificationSetting::getColumnValueOptions(['purchase_requests'], 'approval_date');
        $this->assertSame(1, count(array_filter(array_keys($days), fn ($key) => $key === '2026-10-16')), 'One option per calendar day.');
        $this->assertSame(['2026-10-16 08:00:00'], $rule->getValues()['approval_date'], 'Stored keys stay as saved.');
        $this->assertStringContainsString(':', NotificationSetting::typedValueLabels(['purchase_requests'], 'approval_date', ['2026-10-16 08:00:00'])['2026-10-16 08:00:00']);
    }

    public function test_sanitizing_drops_values_that_do_not_fit_the_column_type_but_keeps_valid_legacy_ones(): void
    {
        $clean = NotificationSetting::sanitizeSettings([
            'tables' => ['purchase_requests'], 'actions' => ['update'],
            'columns' => ['total_estimated_cost', 'urgency_level'],
            'values' => ['total_estimated_cost' => ['12', 'abc'], 'urgency_level' => ['high', 'anything']],
        ]);

        $this->assertSame(['12'], $clean['values']['total_estimated_cost']);
        $this->assertSame(['high'], $clean['values']['urgency_level'], 'Predefined columns keep only values from their option set.');
    }

    public function test_value_pickers_have_translated_labels_in_fa_and_fr(): void
    {
        foreach (['fa', 'fr'] as $locale) {
            app()->setLocale($locale);

            foreach (['validation_value_max', 'validation_value_invalid', 'new_value', 'yes', 'no', 'helper_column_values_typed'] as $key) {
                $this->assertNotSame("resources/notificationSetting/strings.form.{$key}", __("resources/notificationSetting/strings.form.{$key}"));
            }
        }
    }

    public function test_creating_a_rule_stores_values_per_column(): void
    {
        $this->request(['urgency_level' => 'high']);
        $this->actAsViewer();

        $this->create($this->createData())->assertHasNoActionErrors();

        $rule = NotificationSetting::latest('id')->first();
        $this->assertSame(['urgency_level' => ['high']], $rule->getValues());
        $this->assertSame(['urgency_level'], $rule->getColumns());
    }

    public function test_hostile_form_payloads_are_rejected_or_neutralized_without_storing_junk(): void
    {
        $this->request(['urgency_level' => 'high']);
        $this->actAsViewer();
        $before = NotificationSetting::count();

        $rejected = [
            'tables as a string' => $this->createData(['tables' => 'purchase_requests']),
            'tables as null' => $this->createData(['tables' => null]),
            'tables without view access' => $this->createData(['tables' => ['payments']]),
            'unknown table' => $this->createData(['tables' => ['users']]),
            'actions as a string' => $this->createData(['actions' => 'update']),
            'actions unknown' => $this->createData(['actions' => ['restore']]),
            'columns as a string' => $this->createData(['columns' => 'urgency_level']),
            'column not in the table' => $this->createData(['columns' => ['nope'], 'values' => []]),
            'value over 255 characters' => $this->createData(['values' => ['urgency_level' => [str_repeat('a', 256)]]]),
            'more than 50 values' => $this->createData(['values' => ['urgency_level' => array_map('strval', range(1, 51))]]),
            'foreign key id that does not exist' => $this->createData(['columns' => ['user_id'], 'values' => ['user_id' => ['99999999']]]),
            'values for a column as a string' => $this->createData(['values' => ['urgency_level' => 'high']]),
            'users as a string' => $this->createData(['users' => 'abc']),
        ];

        foreach ($rejected as $label => $data) {
            try {
                $this->create($data)->assertHasActionErrors();
            } catch (ExpectationFailedException) {
                $this->fail("{$label} was not rejected.");
            }

            $this->assertSame($before, NotificationSetting::count(), "{$label} must store nothing.");
        }

        $this->create(['notification_type' => 'in_app'])->assertHasActionErrors();
        $this->assertSame($before, NotificationSetting::count());

        $this->create($this->createData())->assertHasNoActionErrors();
        $this->assertSame($before + 1, NotificationSetting::count(), 'Positive control: the clean payload is stored.');
    }

    public function test_unknown_or_unselected_value_keys_and_a_scalar_values_field_are_dropped_not_stored(): void
    {
        $this->request(['urgency_level' => 'high']);
        $this->actAsViewer();

        foreach ([
            ['values' => 'high'],
            ['values' => ['notes' => ['x'], 'urgency_level' => ['high']]],
            ['values' => ['password' => ['x'], 'urgency_level' => ['high']]],
            ['values' => ['urgency_level' => [['high'], 'high']]],
        ] as $override) {
            $before = NotificationSetting::count();
            $this->create($this->createData($override));

            if (NotificationSetting::count() > $before) {
                $values = NotificationSetting::latest('id')->first()->getValues();

                $this->assertSame([], array_diff(array_keys($values), ['urgency_level']), 'Only selected watched columns keep values.');
                $this->assertSame([], array_filter(array_merge(...array_values($values ?: [[]])), 'is_array'), 'No nested arrays are stored.');
            }
        }
    }

    public function test_sanitize_settings_clears_stale_columns_and_rebuilds_a_clean_shape(): void
    {
        $this->actAsViewer();

        $clean = NotificationSetting::sanitizeSettings([
            'tables' => ['purchase_requests', 'users', 5],
            'actions' => ['update', 'restore'],
            'columns' => ['urgency_level', 'iban', 'nope', ['x']],
            'values' => ['urgency_level' => ['high', ['nested'], 'low'], 'nope' => ['x'], 'iban' => ['1']],
            'users' => ['3', 3, 'abc', ['x']],
            'is_active' => 'true',
            'junk' => 'x',
        ]);

        $this->assertSame(['purchase_requests'], $clean['tables']);
        $this->assertSame(['update'], $clean['actions']);
        $this->assertSame(['urgency_level'], $clean['columns']);
        $this->assertSame(['urgency_level' => ['high', 'low']], $clean['values']);
        $this->assertSame([3], $clean['users']);
        $this->assertTrue($clean['is_active']);
        $this->assertArrayNotHasKey('junk', $clean);

        $this->assertSame([], NotificationSetting::sanitizeSettings('x')['tables']);
        $this->assertSame([], NotificationSetting::sanitizeSettings(null)['actions']);

        $switched = NotificationSetting::sanitizeSettings(['tables' => ['payments'], 'actions' => ['update'], 'columns' => ['urgency_level', 'payment_date']]);
        $this->assertSame(['payment_date'], $switched['columns'], 'Columns of the previous table are cleared when the tables change.');

        $noUpdate = NotificationSetting::sanitizeSettings(['tables' => ['payments'], 'actions' => ['create'], 'columns' => ['payment_date'], 'values' => ['payment_date' => ['x']]]);
        $this->assertArrayNotHasKey('columns', $noUpdate);
        $this->assertArrayNotHasKey('values', $noUpdate);
    }

    public function test_editing_a_per_column_rule_keeps_its_values_and_saves_them_back(): void
    {
        $this->request(['urgency_level' => 'high']);
        $actor = $this->actAsViewer();
        $rule = NotificationSetting::create([
            'settings' => ['is_active' => true, 'tables' => ['purchase_requests'], 'actions' => ['update'], 'columns' => ['urgency_level'], 'values' => ['urgency_level' => ['high']], 'users' => [$actor->id]],
            'notification_type' => 'in_app',
            'user_id' => $actor->id,
        ]);

        Livewire::test(ManageNotificationSettings::class)
            ->mountTableAction('edit', $rule)
            ->assertSet('mountedActions.0.data.settings.values', ['urgency_level' => ['high']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(['urgency_level' => ['high']], $rule->fresh()->getValues());
    }

    public function test_changing_the_tables_clears_stale_columns_in_the_form(): void
    {
        $this->actAsViewer(['purchase_request.view', 'payment.view']);

        Livewire::test(ManageNotificationSettings::class)
            ->mountAction('create')
            ->fillForm(['settings.tables' => ['purchase_requests'], 'settings.actions' => ['update'], 'settings.columns' => ['urgency_level']])
            ->set('mountedActions.0.data.settings.tables', ['payments'])
            ->assertSet('mountedActions.0.data.settings.columns', []);
    }

    private function valueField(string $table, string $permission, string $column): ?Select
    {
        $this->actAsViewer([$permission]);

        $component = Livewire::test(ManageNotificationSettings::class)
            ->mountAction('create')
            ->fillForm(['settings.tables' => [$table], 'settings.actions' => ['update'], 'settings.columns' => [$column]]);
        $schema = (new \ReflectionMethod($component->instance(), 'getMountedActionSchema'))->invoke($component->instance());

        return collect($schema->getFlatComponents(true))->first(fn ($field): bool => $field instanceof Select && $field->getName() === 'settings.values.'.$column);
    }

    public function test_a_column_with_predefined_options_offers_only_those_options_and_no_plus(): void
    {
        $field = $this->valueField('proforma_invoices', 'proforma_invoice.view', 'transport_mode');

        $this->assertNotNull($field);
        $this->assertFalse($field->hasCreateOptionActionFormSchema());
        $this->assertSame(['air', 'sea', 'land', 'multimodal'], array_keys($field->getOptions()));
        $this->assertSame(__('resources/proformaInvoice/strings.general.transport_modes.sea'), $field->getOptions()['sea']);
    }

    public function test_a_free_text_column_keeps_the_plus_input(): void
    {
        $field = $this->valueField('purchase_requests', 'purchase_request.view', 'pr_number');

        $this->assertNotNull($field);
        $this->assertTrue($field->hasCreateOptionActionFormSchema());
    }

    public function test_values_outside_the_predefined_set_are_dropped_on_sanitize(): void
    {
        $clean = NotificationSetting::sanitizeSettings([
            'tables' => ['proforma_invoices'],
            'actions' => ['update'],
            'columns' => ['transport_mode'],
            'values' => ['transport_mode' => ['sea', 'cfr', 'Sea', 'air']],
        ]);

        $this->assertSame(['sea', 'air'], $clean['values']['transport_mode']);
        $this->assertSame(['sea' => __('resources/proformaInvoice/strings.general.transport_modes.sea')], NotificationSetting::typedValueLabels(['proforma_invoices'], 'transport_mode', ['sea']));
        $this->assertNull(NotificationSetting::predefinedOptions(['proforma_invoices'], 'invoice_no'));
        $this->assertNull(NotificationSetting::predefinedOptions(['purchase_requests'], 'notes'));
        $this->assertSame([], NotificationSetting::sanitizeSettings(['tables' => ['proforma_invoices'], 'actions' => ['update'], 'columns' => ['transport_mode'], 'values' => ['transport_mode' => ['cfr']]])['values']);
    }

    public function test_every_selectable_column_of_every_notification_table_gets_a_picker_and_closed_sets_are_wired(): void
    {
        $tables = array_keys(NotificationSetting::scannableModels());
        $this->actAsViewer(array_map(fn (string $class): string => \Illuminate\Support\Str::snake(class_basename($class)).'.view', NotificationSetting::scannableModels()));
        \App\Services\PredefinedOptions::flush();
        $kinds = ['foreign' => 0, 'predefined' => 0, 'boolean' => 0, 'other' => 0];

        $this->assertCount(9, $tables);

        foreach ($tables as $table) {
            $class = NotificationSetting::scannableModels()[$table];

            foreach (NotificationSetting::selectableColumns($table) as $column) {
                $closed = app(\App\Services\PredefinedOptions::class)->optionsFor($class, $column);
                $predefined = NotificationSetting::predefinedOptions([$table], $column);
                $this->assertSame($closed !== null, $predefined !== null, "{$table}.{$column} wiring");

                $kind = match (true) {
                    NotificationSetting::isForeignKeyColumn([$table], $column) => 'foreign',
                    $predefined !== null => 'predefined',
                    NotificationSetting::columnKind([$table], $column) === 'boolean' => 'boolean',
                    default => 'other',
                };
                $kinds[$kind]++;
            }
        }

        $this->assertGreaterThan(25, $kinds['foreign']);
        $this->assertGreaterThan(15, $kinds['predefined']);
        $this->assertSame(array_sum($kinds), array_sum(array_map(fn (string $table): int => count(NotificationSetting::selectableColumns($table)), $tables)));
    }
}
