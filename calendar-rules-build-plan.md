# Calendar Rules — Build Plan (BMS-CM, Laravel 12 / Filament v4)

> **LIVING DOCUMENT — READ THIS BOX FIRST, EVERY TIME.**
> - **Plan version: 25** (last updated 2026-10-09 — see section 19, the newest handoff). The Lead bumps this number and the "Latest changes" list below on every edit.
> - **Before starting ANY step (and again after any pause or a "proceed"), re-read this file.** Do not rely on memory of an earlier read or on an earlier prompt: the plan changes between steps.
> - **Precedence when text conflicts:** section 19 (session handoff) > section 18 (open list) > section 13 (3b second fix pass) > section 12 (3b review fixes) > section 11 (performance) > section 10 (landing tab add-on, Step 12) > section 9 (resolved user decisions) > section 8 (audit corrections) > sections 1-7. A later section always wins.
> - **Status:** Steps 1-10 and 12 built, reviewed and approved; Alerts hub done except the event-alert bell icon; notification-settings pass under review. LAST: step 11 (final docs check + Fable 5.1 review + QA + production checklist).
> - **Latest changes (newest first):**
>   - v25: section 19 = SESSION HANDOFF (state, final shapes, settled decisions, dropped items, review status, open list, production steps, known limits). Read it first when resuming.
>   - v24: section 18 = the LIVE list of all open work, issues and decisions (the Lead updates it on every change; read it last).
>   - v23: the nested "Alerts" parent menu did NOT work (Filament v4 only wires `parentItem` for Pages and manual NavigationItems; `getNavigationParentItem()` on a Resource is inert). Section 17.1 is REPLACED by a flat "Alerts" navigation group + a top sub-navigation switcher on both list pages (section 17.1b). Legacy flat notification value lists were removed (no production data).
>   - v22: notification-settings pass reviewed and approved (all 16 items verified); event-alert icon intentionally left as the existing action-specific icons.
>   - v21: checkpoint 10+12 reviewed and fixed; Activity button moved into the calendar grid widget (soft-deleted records included, tagged); Alerts hub nested menu + calendar bell icon DONE and tested (cross-links reverted); only the EVENT-alert bell icon (notification classes) is still open until the notification review ends.
>   - v20: section 17 = "Alerts hub" (nested menu + bell icons), user-approved; cross-links between Notification Settings and Calendar Rules were evaluated and DROPPED.
>   - v19: user decision: calendar jobs use the DEFAULT queue (no dedicated `calendar` queue, no worker flag). Every job calls `$this->onQueue(config('calendar.queue'))`, `config/calendar.php` `queue` = `env('CALENDAR_QUEUE', 'default')`. This supersedes the "queue `calendar`" lines in sections 11, 12 and 14 and the worker flag in section 16.
>   - v18: section 16 = production checklist (migrations by path, permissions, queues, scheduler cron).
>   - v17: step 9 reviewed, fixed and APPROVED (5 defects fixed by the reviewer: phantom blank cells, forged URL/property types, rule-name leak for unviewable modules, orphan subject in the day table, a comment; the Lead added localized module ordering in the day table, a calendar-toggled listener and removed a stray comment). Slice A of checkpoint 10+12 may now register the Activity action in `Dashboard::getHeaderActions()`. Lessons 17-20 added to section 14.
>   - v16: user decision supersedes v15: checkpoint 10+12 is built by TWO coder agents IN PARALLEL (slice A = step 10 activity button, slice B = step 12 landing tab) under the strict file-ownership table in section 15, then reviewed by TWO reviewers in parallel (one per slice), then the Lead consolidates the permission test. Both agents re-read the plan first.
>   - v15: user decision: steps 10 (activity button) and 12 (landing "Needs attention" tab) are built TOGETHER as ONE checkpoint ("10+12") after step 9 is approved, by the same agent, sequentially (10 first, then 12): they are file-disjoint except for the three lang files and `CalendarPermissionTest`, so no parallel agents (merge conflicts in shared files cost more than the time saved). One review-and-fix pass covers both. Step 11 stays last.
>   - v14: steps 7-8 reviewed and fixed (View modal crash, tampered-payload gating, merge/duplicate leaks); exact-duplicate lookup and date-path options are now visibility/permission scoped; lessons 14-16 added to section 14. Docs for steps 1-8 are done (filamentPattern 1.32, localizationPattern, calendarPattern).
>   - v13: section 14 = consolidated LESSONS LEARNED from all reviews so far. Re-read it before every step and self-check your diff against it before reporting.
>   - v12: user decision: step 12 (landing "Needs attention" tab, section 10) is built BEFORE step 11, so the final Fable 5.1 review and QA pass cover the landing tab too. The "one permission test" (`CalendarPermissionTest`) must include the landing-tab surface before step 11 starts.
>   - v10: 4-6 checkpoint built by the agent. The Lead SPLIT the services: `Sync\CalendarEngine` (sync, preview, hits), `Sync\CalendarRouter` (suspended, isSuspended, flushRoutes, bumpRoutesVersion, touch, computeWatchedColumns, routing), `CalendarFilterTree` (shared plain class, injected, root namespace), `Display\CalendarRange` and `Display\CalendarPresenter`. Every plan mention of `CalendarEngine::touch/suspended/flushRoutes/computeWatchedColumns` now means `CalendarRouter`; observers inject `CalendarRouter`; tests have a `router()` helper. A related-row create that no subject references is now a no-op; a non-array stored leaf makes a rule invalid.
>   - v9: section 13 = second fix pass for Part 3b (2 HIGH: sweep above 65k ids, syncSubject ignoring invalid rules; 5 MED; LOW cleanup). Last fix cycle for these findings.
>   - v8: section 12 = Part 3b review fixes (3 HIGH, many MED). The agent must do section 12 BEFORE the 4-6 checkpoint. Schema changes must never drop and re-create tables silently: edit the migration file, apply to dev with a script or a `--path` migrate, and report each schema change.
>   - v7: Part 3a review fixes applied by the Lead: strict `validatePath`, month arithmetic in `CalendarRange::shiftMonths`, `parseIso()` fallback, `CalendarModules` registry memo, User column blocklist (`ip`, `phone`, `email`, `settings`, `image`, `role`), user-aware gating in `constraintsFor()`/`suggest()` (optional `?User`; the engine passes none), typed relation methods on 5 models, `SharedUserIdsCast` dedupe, services no longer `final`. Lessons for all later steps are in the Lead's last prompt and in section 11.
>   - v6: section 11 (performance hardening) and section 10 (landing "Needs attention" tab as its own new tab).
>   - v5: section 9 (admins get NO module-permission bypass; rule export yes with the app's own plain `write()` exporter; agent may run `migrate` only by `--path` for the calendar/activity_log files). Named stamp indexes on `calendar_rules`; the activity_log migration is ONE merged file.
>   - v4: section 8 (audit corrections).
> - If something in your task conflicts with this box or a later section, STOP and report; do not pick one silently.

Hand this whole file to the implementing agent. The Lead (the session that wrote it) will review every slice afterwards.
Decisions in §1 are FINAL, made by the user. Do not re-ask, re-open, or "improve" them.

---

## 0. Mandatory pre-work (before writing any code)

1. Read, in order: `CLAUDE.md`, `.claude/skills/code-reviewer/SKILL.md`, `.claude/skills/laravel-performance/SKILL.md`, `app/Filament/filamentPattern.md`, `app/Filament/Widgets/widgetsPattern.md`, `app/Services/servicesPattern.md`, `app/Models/modelsPattern.md`, `app/Utils/helpersPattern.md`, `app/Jobs/jobsPattern.md`, `app/Observers/observersPattern.md`, `lang/localizationPattern.md`, `resources/views/viewsPattern.md`, `resources/css/stylesPattern.md`, `app/Livewire/livewirePattern.md`, `tests/testPattern.md`, `gotchasPattern.md`. Where a pattern file conflicts with CLAUDE.md, the pattern file wins.
2. Scan at least 3 live references for every artifact type you create. Closest analogs:
   - Master-shaped resource with a form: `NotificationSettingResource` (+ `Master/NotificationSettingResource/Traits/Form.php`, `ModelInspector`)
   - Dashboard widget + blade: `PipelineStallWidget` + `resources/views/filament/widgets/pipeline-stall.blade.php`
   - Standalone header action class: `ReturnForRevisionAction`, `ManageCustomAttributesAction`, `HasDeskReferenceAction`
   - Export job: `app/Jobs/ExportBanks.php`
   - Topbar: `resources/views/filament/partials/work-actions.blade.php`
   - Reference UI (read-only, DO NOT copy code or Material tokens): `D:\DEV-ENV\Fateh\app\Livewire\Dashboard\Tab\Calendar.php`, `...\Presentation\CalendarPresenter.php`, `D:\DEV-ENV\Fateh\app\Values\CalendarRange.php`, `D:\DEV-ENV\Fateh\resources\views\livewire\dashboard\tab\calendar\{month,view-header,mini-month,mobile-list}.blade.php`
3. Hard project rules that apply to every file:
   - **Zero comments** in code (PHPDoc on public API methods only; migrations may use `->comment()` on non-obvious columns per modelsPattern §9). No `dd()/dump()`.
   - Methods ≤ ~20 lines, single responsibility. Eloquent only; `->when()/->unless()`; eager-load; `SmartCacheManager` for model-scoped caches.
   - Translate every user-facing string in all 3 locales (en, fa, fr) the same turn. fa: use «کاراکتر» never «نویسه».
   - Role hierarchy is inverted (`admin_junior` = highest trust). Never infer seniority from enum names. Admin check = `User::isAdmin()` (junior + mid + senior) — user-approved.
   - No `ForceDeleteAction`. No `app/Policies/`. Child models get no permission rows (`calendar_hit.*` must NOT be seeded).
   - CSS: only `--custom-*` / `--google-*` tokens; never hardcode a color that has a token; no glass/3D utilities. After editing any `FilamentAssets`-registered file run `php artisan filament:assets`. `fi-custom.css` is registered → run it.
   - Never run cache helpers synchronously inside a Livewire action. Never embed a Livewire component in a topbar render hook.
   - Windows: never give single-quoted inline-PHP tinker one-liners; write a script file. Erase every temp/probe file the same turn.
   - No browser/chromium verification is available. Verify by compile checks, `php -l`, `./vendor/bin/pint`, and the tests below.
   - Every change ships with its own test in the same turn. Run tests sparingly: targeted `--filter` at slice end, one full run only at the very end.

---

## 1. FINAL user decisions (non-negotiable)

| # | Decision |
|---|---|
| D1 | Install `spatie/laravel-activitylog`. `log_name = 'calendar'`. Events tied to the subject record (subject morph). Descriptions human-readable (rule, item label, dates, status), never bare ids. Kept forever. |
| D2 | **No "project" concept.** No `project_id`, no project resolver, no `(project_id, event_date)` index. Day panel grouped by **module** (module label, alphabetical, all listed). Filters = **rule** and **module**. Activity search = search a **record** of any enabled module. |
| D3 | The 8 modules: PurchaseRequest, ProformaInvoice, RegisteredOrder, PurchaseOrder, Payment, Shipment, Custom, BankProfile. NOT Correspondence. Source list: `config/workspace.php`. |
| D4 | Calendar = **new FIRST tab of the existing tabbed `Dashboard` page** (`Dashboard::TABS`), NOT a resource index. |
| D5 | `CalendarRuleResource` is **Master-shaped**: single `ManageCalendarRules` page, no create/edit routes, **Master Data nav group (`base`)**, `navigationSort = 13`. Create / edit / view open as **modals (slide-over)**. This deviates from "Master = view-only infolist, header actions []" — record it as a user-approved exception in `filamentPattern.md`. |
| D6 | Rule form = **one scrolling form with Sections** (user-approved exception to "tabs over sections for 5+ fields"; note it in `filamentPattern.md`). Live preview ("Matches N items" + first 5: item label, date) sits at the bottom of the form. |
| D7 | Rule color = **fixed palette of 8 theme-safe colors** (a `ToggleButtons`/radio-style picker storing a palette key or hex from a constant list) — NOT a free ColorPicker. Must read well in all 5 themes and dark mode. |
| D8 | UI = **Fateh split layout**: month grid one side, selected-day side panel on the other. **Raised rounded day tiles**; today tinted; selected filled; small count badge (`+N`) on busy days; up to 3 rule-color dots; **red ring** (+ small alert icon) on days with unresolved overdue Action-required hits. Header range label opens a **mini-month/year jump popover**. Prev / Today / Next. Month ↔ Agenda toggle. **Mobile (< md) shows a day-grouped agenda list instead of the grid.** Legend row under the grid (rule color chips). **NOT wanted:** maximize/fullscreen toggle, help modal. |
| D9 | Both calendars: Gregorian **and** Jalali via the existing `calendar_type` contract (`isJalaliCalendar()`), RTL/LTR, EN/FA/FR. Re-render on the `calendar-toggled` event. |
| D10 | Rules are managed ONLY from the resource page (nav menu) **and** a topbar **Quick-create** entry that opens the rule create modal. **No "New rule" button anywhere on the calendar tab.** Calendar tab header has the **Activity** button only. No "My rules" drawer. |
| D11 | Alerts = Filament database (bell) notifications. One notification per rule per alert day listing first 3 items by label (no project names). Single item → opens record. Grouped → opens Dashboard calendar day filtered to that rule. Action: "Seen" (mark as read). Pluggable channel layer: list in `config/calendar.php`; new channel = one class + one config entry. No login toast. |
| D12 | Admins = `User::isAdmin()`. Scheduler runner: add `php artisan schedule:work` to the `composer run dev` concurrently line (user owns the production cron). Alerts 07:00, rebuild 02:00, `Asia/Tehran`. "Everyone" visibility → one notification per user who holds the module's `.view` permission; no cap. Relation picker lists depth-1 + an explicit "add deep path" picker (hard cap 6 hops). Subject registry reuses `config/workspace.php`. Unique-job locks use the default `file` cache store (supports atomic locks). |

---

## 2. Verified facts (checked against the repo)

- `vendor/filament/query-builder` is installed. `Filament\QueryBuilder\Forms\Components\RuleBuilder` is a standalone form component; `Filament\QueryBuilder\Models\Scopes\QueryBuilderScope::make(array $rules, array $constraints)` applies stored rule JSON to any Eloquent builder with no Livewire context. A `Constraint` with dotted `->attribute('a.b.col')` becomes `whereHas('a.b', …)`; nested dotted `whereHas` is native Eloquent ⇒ unlimited-depth paths need zero custom SQL. **Re-verify these two signatures in vendor before coding.**
- `User::isAdmin()` = `hasRole([ADMIN_JUNIOR, ADMIN_MID, ADMIN_SENIOR])`.
- `.env`: `CACHE_STORE=file`, `QUEUE_CONNECTION=database`, `APP_TIMEZONE=Asia/Tehran`. No scheduler runner exists; `routes/console.php` has no schedule entries.
- `Dashboard::TABS` const exists; adding a tab is a one-line entry (`widgetsPattern.md`).
- Topbar Quick-create is built in `resources/views/filament/partials/work-actions.blade.php` from a `$resources` array of 9 operational FQCNs: each gated by `canCreate() && canViewAny()`, grouped by `getNavigationGroup()`, linked to `route('filament.dashboard.resources.{slug}.create')`. A Master-shaped resource has no `.create` route.
- Fateh's calendar is a server-rendered Livewire grid (presenter + range value object, `wire:click="selectDate"`), Jalali-only/RTL-only; we port the UX, not the code.

---

## 3. Data model

Migrations in `database/migrations/` (modelsPattern §9: comments on non-obvious columns, `down()` = dropIfExists, **no DB `unique` on SoftDeletes tables**).

### `calendar_rules` (SoftDeletes)
`id`; `user_id` unsignedBigInteger nullable (owner, no FK — PR shape); `name` string; `subject` string (module FQCN); `filters` json (RuleBuilder tree); `extra_paths` json nullable (deep paths the user added); `date_path` string (dotted path to the date column, e.g. `eta` or `registeredOrder.delivery_date`); `day_shift` smallInteger default 0; `lead_times` json (e.g. `[7,3,1]`); `on_day` boolean default true; `type` string (`heads_up|action`); `color` string(20) (palette KEY, not hex: `sky|emerald|amber|rose|violet|teal|orange|slate`, cast to a `CalendarColor` enum in `Master/CalendarRuleResource/Enums/CalendarColor.php` with `HasLabel`, `HasColor`, `cssVar()`); `visibility` string (`me|everyone|users`); `shared_user_ids` json nullable; `is_active` boolean default true; `related_models` json (derived, FQCN list); `fingerprint` char(40); `conditions_hash` char(40); `updated_by_id` nullable; timestamps; softDeletes.
Indexes: `[subject, is_active]`, `fingerprint`, `conditions_hash`, `[user_id, deleted_at]`, `[deleted_at, created_at]`.

### `calendar_hits` (derived data: no SoftDeletes, no UserStamps)
`id`; `calendar_rule_id` FK `constrained()->cascadeOnDelete()`; `subject_type` string; `subject_id` unsignedBigInteger; `label` string (denormalized item label — grid/agenda never morph-load); `event_date` date; `alerts_sent` json (default `{}`); `overdue_count` unsignedTinyInteger default 0; `synced_at` timestamp nullable (run stamp for the stale sweep); timestamps.
`unique([calendar_rule_id, subject_type, subject_id], 'calendar_hits_rule_subject_unique')`, `index('event_date')`, `index([subject_type, subject_id])`.

### `activity_log`
Vendor migration from `spatie/laravel-activitylog` (`composer require`, `php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"`). Keep `delete_records_older_than_days` effectively unlimited.

### Permissions
Add `calendar_rule.view|create|edit|delete|restore` to `database/seeders/DatabaseSeeder.php` (permission list ≈ line 19). `ResourceAuthorizationIntegrityTest::test_every_module_prefix_has_the_full_action_set_seeded` enforces it. Run `Permission::firstOrCreate` for the 5 rows on the dev DB too. **Do not** create `calendar_hit.*`.

### `config/calendar.php`
```php
return [
    'channels' => ['database'],
    'alert_time' => '07:00',
    'rebuild_time' => '02:00',
    'max_overdue_alerts' => 3,
    'max_path_depth' => 6,
    'preview_limit' => 5,
    'palette' => ['sky', 'emerald', 'amber', 'rose', 'violet', 'teal', 'orange', 'slate'],
];
```
Each palette key maps to a CSS variable `--cal-color-{key}` defined in `fi-custom.css` with light and dark values, checked for contrast against all 5 themes (`config/palettes.php` / `themes.css`) in light and dark mode. `ConfigTest` drift check: every palette key has a `--cal-color-{key}` rule in `fi-custom.css` and a label in the 3 locales. The color field validates with `Rule::in(config('calendar.palette'))`; tiles/dots use `style="--cal-c: var(--cal-color-{{ $key }})"`. The planner chose these 8 keys; the user may swap any.

---

## 4. Build steps (strict dependency order)

### Step 1 — Package, migrations, seeder, config
As §3. Add `schedule:work` to `composer.json` `scripts.dev` concurrently command.

### Step 2 — Models
- `app/Models/CalendarRule.php` — `use SoftDeletes, Relationships (aliased ExclusiveRelationships per modelsPattern §11), UserStamps, HasVisibility, LogsActivity`. Casts: `filters/extra_paths/lead_times/shared_user_ids/related_models => array`, `on_day/is_active => bool`, `type => RuleType`, `visibility => Visibility`. `getActivitylogOptions(): LogOptions` → `useLogName('calendar')->logOnly([...business fields])->logOnlyDirty()`. `$fillable` ends with `user_id, updated_by_id`.
- Enums `app/Filament/Resources/Master/CalendarRuleResource/Enums/RuleType.php` (`HeadsUp`, `Action`) and `Visibility.php` (`Me`, `Everyone`, `Users`): implement `HasLabel` (+ color/icon where useful), labels via `__()` inside the method.
- `app/Models/Traits/CalendarRule/Relationships.php` (`owner(): BelongsTo` User, `hits(): HasMany`).
- `app/Models/Traits/CalendarRule/HasVisibility.php`:
  - `scopeVisibleTo(Builder $q, User $u): Builder` — admin: unfiltered; else `visibility='everyone' OR user_id=$u->id OR whereJsonContains('shared_user_ids', $u->id)`.
  - `isEditableBy(User $u): bool` — owner or `isAdmin()`.
  - `recipients(): Collection<User>` — owner + shared (or all users for `everyone`) filtered by `User::permission("{prefix}.view")` where prefix = `Str::snake(class_basename($this->subject))`; includes admins.
  - Hashing helpers: `static canonical(array $filters): array` (re-index list arrays, `ksort` recursively, strip RuleBuilder's random 4-char item uuids), `computeFingerprints(): void`.
- `app/Models/CalendarHit.php` — `rule(): BelongsTo`, `subject(): MorphTo`; `scopeVisibleTo(Builder, User)`; `scopeForDay(Builder, Carbon)`; `scopeOverdue(Builder)` (`whereHas('rule', type=action)->whereDate('event_date','<', today())`); casts `event_date => date`, `alerts_sent => array`. Uses the plain per-domain `Relationships` trait (no user stamps — like the `*Item` models).
- `CalendarHit::scopeVisibleTo(Builder $q, User $u)`: non-admin → `whereIn('subject_type', CalendarModules::viewableBy($u))` AND `whereHas('rule', fn ($r) => $r->visibleTo($u))`; admin → rule-visibility only. **Related tables never influence visibility.**
- Factories `CalendarRuleFactory`, `CalendarHitFactory` (`forSubject(Model)` state; `is_active => true` unconditionally — testPattern lesson).

### Step 3 — Services subsystem `app/Services/Calendar/` (+ new `calendarPattern.md`)

Exact contracts (do not deviate):

```php
final class CalendarModules {
    public static function all(): array;                       // [FQCN => ['label'=>string,'route'=>string,'identifier'=>string]] built from config('workspace.resources')
    public static function viewableBy(User $u): array;         // FQCNs where userCan($class) (helpers) ; admin => all
    public static function label(Model|string $m): string;     // module label (lang)
    public static function itemLabel(Model $record): string;   // $record->{SCANNABLE_IDENTIFIER} ?? '#'.$id
    public static function url(Model $record): string;         // edit route of that module
}

final class CalendarPathResolver {
    public function columns(string $class): array;             // [col => 'date'|'number'|'boolean'|'text']; Schema::getColumns cached 1 day 'calendar_columns:{table}'; blocklist password,remember_token,two_factor_*,deleted_at
    public function relations(string $class): array;           // [method => ['related'=>FQCN,'single'=>bool]]; reflection over public zero-arg methods with a declared return type subclass of Relation, declared under app/Models (trait methods resolve to the composing class); MorphTo EXCLUDED; cached 1 day 'calendar_relations:{class}'
    public function validatePath(string $subject, string $path): array;   // returns hops; throws ValidationException on undeclared hop, cycle (visited-class set), depth > config max
    public function suggest(string $subject, string $typedPrefix): array;  // next-hop options for the deep-path picker
    public function constraintsFor(string $subject, array $extraPaths): array;      // builder side: depth-1 + extra paths; names = dotted attribute paths
    public function constraintsForRule(CalendarRule $rule): array;                  // engine side: only types present in the stored tree
    public function datePathOptions(string $subject, array $extraPaths): array;     // date columns reachable ONLY through single-valued hops (BelongsTo/HasOne/MorphOne)
    public function relatedModels(CalendarRule $rule): array;                       // FQCNs of every hop in filters + date_path
    public function summaries(CalendarRule $rule): array;                           // readable "path operator value" lines for the infolist
}
```
Constraint rules: `*_id` columns that have a declared BelongsTo are exposed as a `RelationshipConstraint` on the relation, not a raw int. Constraint labels: "Relation → Column" using `resources/{camel}/strings.form.{column}` when present else `Str::headline`. Use `DateConstraint` / `NumberConstraint` / `BooleanConstraint` / `TextConstraint` by column type. A path through an `is_active`-scoped BelongsTo to an inactive row simply doesn't match — document, don't special-case.

```php
final class CalendarEngine {
    public function syncRule(CalendarRule $rule): void;             // purge hits if inactive; else query = $subject::query()->tap(QueryBuilderScope::make($rule->filters['rules'] ?? [], constraintsForRule))->with(dateRelationPath); chunkById(500); upsert hits keyed (rule, subject_type, subject_id) setting label,event_date,synced_at (reset alerts_sent=[] & overdue_count=0 + log date_changed when event_date changed; log matched for new); afterwards delete rows with synced_at < runStamp (log cleared)
    public function syncSubject(string $class, int $id): void;      // per active rule on $class: whereKey($id)->matches ? upsert : delete
    public function touch(Model $model): void;                      // in-memory routing map (SmartCacheManager::remember('CalendarRule',['type'=>'routing'],60,…)): subject class => dispatch SyncCalendarSubject; related class => dispatch SyncCalendarRule per affected rule; else no-op, zero DB
    public function preview(string $subject, array $filters, array $extraPaths, string $datePath, int $shift): array; // ['count'=>int,'items'=>[['label','date']] max config preview_limit]
    public function eventDateFor(Model $record, string $datePath, int $shift): ?Carbon; // startOfDay + addDays(shift)
}
final class CalendarAlerts {
    public function sendDue(Carbon $today): void;
    public function dueHitsFor(CalendarRule $rule, Carbon $today): Collection;
}
final class CalendarActivity {
    public function log(string $event, Model $record, CalendarRule $rule, array $props = []): void;  // activity('calendar')->performedOn($record)->withProperties([event, rule_name, label, event_date, old_date, status, lead])->log($event); description rendered at READ time from resources/calendarRule/strings.activity.{event}
    public function forRecord(Model $record, bool $all): Collection; // default: matched|cleared|date_changed|alert_sent; $all adds rule rows (CalendarRule subject for rule ids seen in the record's rows) + 'seen' rows derived from notifications (data->rule_id, read_at not null) — no extra write path
}
final readonly class CalendarRange {
    public function __construct(public Carbon $start, public Carbon $end, public string $anchor, public bool $jalali) {}
    public static function forMonth(string $isoAnchor, bool $jalali): self;
    public function days(): array;
    public function label(): string;                // "F Y" in the active calendar (Jalalian / translatedFormat)
    public function weekdayOffset(): int;           // Saturday-first under Jalali, Monday-first otherwise
    public static function shiftMonths(string $iso, int $delta, bool $jalali): string;
}
final class CalendarPresenter {                     // PURE: no auth, no queries — receives already-scoped data
    public function monthCells(CalendarRange $r, string $selectedIso, Collection $hits): array;   // [{iso, day, isToday, isPast, isSelected, count, colors(<=3), hasOverdue}]
    public function agendaDays(CalendarRange $r, Collection $hits): array;                       // day-grouped, overdue first
    public function weekdayLabels(bool $jalali): array;
    public function miniMonth(string $isoAnchor, bool $jalali): array;                            // year/month jump options in the active calendar's numbering
}
```
All navigation/selection state is canonical Gregorian ISO `Y-m-d`; convert only for display. Jalali via `Morilog\Jalali\Jalalian` (already installed). Never re-inline the `calendar_type` check — use `isJalaliCalendar()`.

Alert semantics (implement exactly):
- A lead `L` fires for a hit iff `alerts_sent[L]` is unset AND `event_date - L days <= today() <= event_date`. On-day (`0`) fires iff `on_day` and unset and `today() == event_date`. Missed days: all still-unsent leads whose trigger ≤ today fire, collapsed into the single per-rule-per-day notification.
- Re-arm: if the recomputed `event_date` differs from stored → `alerts_sent = []`, `overdue_count = 0`, activity `date_changed`.
- Overdue (`type = action` only): due when `today() >= event_date + 1 day` AND (`overdue_count == 0` OR `today() >= last overdue date + 7 days`) AND `overdue_count < max_overdue_alerts (3)`. `alerts_sent.overdue` = list of dates sent. Hits disappear the moment the record stops matching, so "until it stops matching" is automatic.
- Heads-up: leaves the calendar after the date (queries exclude `event_date < today()` for heads-up rules; Action-required stays, highlighted overdue).
- **All date comparisons use `today()` / `->startOfDay()`, never `now()`** (gotchasPattern: midnight-boundary rule). A hit dated today is NOT overdue.

### Step 4 — Jobs (`app/Jobs/`, traits `Dispatchable, InteractsWithQueue, Queueable, SerializesModels`, shape of `ExportBanks`)
- `SyncCalendarSubject(string $class, int $id)` — `implements ShouldQueue, ShouldBeUnique`; `uniqueId()` = `"$class:$id"`; `public int $uniqueFor = 120`; dispatched with `->delay(30s)`.
- `SyncCalendarRule(int $ruleId)` — `ShouldBeUnique`; `uniqueFor = 300`; `->delay(120s)`.
- `SendCalendarAlerts` → `CalendarAlerts::sendDue(today())`.
- `RebuildCalendarHits` → dispatch `SyncCalendarRule` (no delay) for every active rule.
- `ExportCalendarHits(array $ids, int $userId, string $locale)` — mirrors `ExportBanks`; re-applies `CalendarHit::visibleTo($user)` to the ids (client-submitted ids are untrusted).
- Each `handle()` wraps in try/`report($e)` per jobsPattern; add the queue-worker restart note (`php artisan queue:restart` after editing any job/engine class).
- `routes/console.php`: `Schedule::job(new SendCalendarAlerts)->dailyAt(config('calendar.alert_time'))->withoutOverlapping();` and `Schedule::job(new RebuildCalendarHits)->dailyAt(config('calendar.rebuild_time'));`.

### Step 5 — Observers (`app/Observers/`, registered manually in `AppServiceProvider::registerObservers()`)
- `CalendarRuleObserver` — `saving()`: set `fingerprint` (sha1 of subject|canonical filters|date_path|day_shift|type), `conditions_hash` (sha1 of subject|canonical filters|date_path), `related_models`. `saved()/restored()`: `SmartCacheManager::invalidate('CalendarRule')` + `SyncCalendarRule::dispatch($rule->id)->delay(...)`. `deleted()`: purge hits + invalidate.
- `CalendarTouchObserver` — `saved/deleted/restored/forceDeleted` → `CalendarEngine::touch($model)`. Register over every concrete class in `app/Models` (same `File::allFiles` scan `NotificationServiceProvider` uses, but registered manually here). The routing map makes unrelated saves cost nothing.
- Update `observersPattern.md`.

### Step 6 — Notification + channel seam
`app/Notifications/CalendarAlertNotification.php`: `__construct(CalendarRule $rule, Collection $hits, string $kind /* lead|on_day|overdue */, int $lead = 0)`; `via()` returns `config('calendar.channels')`; `toDatabase()` in the Filament notification format used by `ModelEventNotification`: title `"{rule name} · N"` (localized), body = first 3 item labels + "+N", actions: single item → `->url(CalendarModules::url($record))`; grouped → `->url(route('filament.dashboard.pages.dashboard', ['cal_date' => $iso, 'cal_rule' => $rule->id]))`; plus `Action::make('seen')->markAsRead()`. `data` carries `rule_id`, `hit_ids`. One notification per recipient per rule per day.

### Step 7 — `CalendarRuleResource` (Master shape)
Files: `app/Filament/Resources/CalendarRuleResource.php`; `Master/CalendarRuleResource/{Traits/{Form,Table,Infolist,Filters}.php, Enums/…, Pages/ManageCalendarRules.php}`.
- Root class: `use CalendarRuleFilters, CalendarRuleForm, CalendarRuleInfolist, CalendarRuleTable, HandleActivation, HasResourcePermissions`. `getNavigationGroup()` → `base`; `$navigationSort = 13`; `getEloquentQuery()` → `->with(['owner','creator','updater'])->withCount('hits')->visibleTo(auth()->user())->withoutGlobalScopes([SoftDeletingScope::class])`; nav badge through `SmartCacheManager::remember('CalendarRule', …)`.
- **Edit gate:** override BOTH families — `canEdit/canDelete/canRestore(Model $record)` AND `getEditAuthorizationResponse/getUpdateAuthorizationResponse/getDeleteAuthorizationResponse/getRestoreAuthorizationResponse(Model $record)` → allow iff Spatie permission AND `$record->isEditableBy(auth()->user())` (filamentPattern §1.5: overriding only `can*` is dead for footer/table actions). Non-owner non-admin sees View + Duplicate only.
- `ManageCalendarRules extends App\Filament\Pages\ManageRecords`: header `CreateAction::make()->icon('heroicon-o-sparkles')->slideOver()`; row `EditAction::make()->slideOver()`. The URL `?action=create` auto-mounts the header `create` action through Filament's vendor `#[Url(as: 'action')] $defaultAction` (verified in vendor/filament/actions/src/Concerns/InteractsWithActions.php) — write NO custom `mount()` code; this is what the topbar shortcut links to. `table()` wrapped in `TableComponents::emptyState(...)`; `->recordUrl(null)`.
- **Form (one scrolling form, Sections; method names `getXxxField()`):**
  `getNameField`; `getSubjectField` (Select, options `CalendarModules::viewableBy()`, `live`, resets filters/date_path/extra_paths on change); `getDeepPathField` (searchable Select, `getSearchResultsUsing(resolver->suggest)`, appends to `extra_paths`, `dehydrated(false)`, displays the chosen path as an expandable "PO → PI → Registered Order → …" line); `getFiltersField` (`Group::make(fn (Get $get) => [RuleBuilder::make('filters')->constraints(resolver->constraintsFor($get('subject'), $get('extra_paths') ?? []))->maxRules(30)->maxNestingDepth(3)])` so the picker rebuilds when subject/extra_paths change); `getDatePathField` (Select from `datePathOptions`); `getDayShiftField` (integer, may be negative); `getLeadTimesField` (Repeater of integer days 0–365, default 7/3/1, `distinct`); `getOnDayField`; `getTypeField` (ToggleButtons, enum, uses `'in'` validation key); `getColorField` (8-color palette from `config('calendar.palette')`, `Rule::in`); `getVisibilityField` + `getSharedUsersField` (Select multiple; options `User::permission("{prefix}.view")`; visible when visibility = `users`; `'*.in'` key); `getIsActiveField`; `getPreviewField` (Action `preview` → `$set('_preview', CalendarEngine::preview(...))` + `Placeholder::make('_preview')->dehydrated(false)->content(view('filament.calendar.preview', …))`); `getSimilarRuleField` (Placeholder: near-duplicate found by `conditions_hash` + `Action::make('merge')` that unions `lead_times` (and ORs `on_day`) into the existing rule, notifies, unmounts).
  Server-side validation (closure rules, not just UI): subject ∈ viewable modules; every `shared_user_ids` user holds `{prefix}.view`; every deep path passes `validatePath`; date_path ∈ `datePathOptions`; lead times ints 0–365. Every rule gets `validationMessages` including implicit ones (`in`, `*.in`, `enum`, `date`) per localizationPattern §3.
- **Duplicates:** `CalendarRuleResource::assertNotDuplicate(array $data, ?int $ignoreId)` called from `CreateAction::mutateFormDataUsing()` / `EditAction::mutateFormDataUsing()`: same `fingerprint` exists → persistent danger Notification with action "Use existing" (the Master shape has no edit route, so the url is the Manage page with the existing rule's edit modal auto-opened: `ManageCalendarRules` url + `?tableAction=edit&tableActionRecord={id}` (vendor `#[Url(as: 'tableAction')]`; verify it opens the edit slide-over)) then `throw new Halt` (same mechanism as `HasUsageGuard`). Near-duplicates (same `conditions_hash`, different timing) only warn (`getSimilarRuleField`).
- **Table (`showXxx()`):** name (+ rule color), subject (module label), type badge, visibility, owner, hits count (icon badge per §1.2), is_active, creator/updater/timestamps (`->adaptiveDateTime()`). Record actions: `ViewAction`, `EditAction` (slide-over), `Action::make('duplicate')` (replicate, `name .= ' (copy)'` localized, `user_id = auth()->id()`, visibility `me`, visible to anyone who can view and holds `calendar_rule.create`), `DeleteAction`, `RestoreAction`. Toolbar: `HandleActivation` bulk activate/deactivate, `DeleteBulkAction::make()->authorizeIndividualRecords()` (so bulk delete respects ownership per row; NotificationSetting precedent), `RestoreBulkAction`. **No ForceDelete. No rule export (cut).**
- **Infolist (`viewXxx()`):** single Section; includes the readable condition summary (`resolver->summaries($rule)`).
- **Filters (`getXxxFilter()`):** subject, type, visibility, owner (`preload()->searchable()`), is_active, `getTrashedFilter()`.

### Step 8 — Topbar Quick-create entry
Edit `resources/views/filament/partials/work-actions.blade.php`: append `CalendarRuleResource::class` to the quick-create source with url `CalendarRuleResource::getUrl('index', ['action' => 'create'])`; add it AFTER the `foreach ($resources …)` loop in the `@php` block, NOT into the `$resources` array (that array also feeds `BMS_RESOURCE_MAP`/recents and builds `.create` routes); label via new key `topbar.quick_create_calendar_rule` in `lang/{en,fa,fr}/resources/general/strings.php`; the group renders as the Master Data group after the 4 pipeline groups, same gate `canCreate() && canViewAny()`, group from `getNavigationGroup()` (the Master Data group), label/icon from the resource. Add the label key to `lang/{en,fa,fr}/resources/general/strings.php` only if needed. Do NOT add it to `BMS_RESOURCE_MAP`/recents (no id route). Update `tests/Feature/Filament/TopbarTest.php` (permission gating, group ordering, entry present with the `?action=create` url, and the Master group label listed after the 4 pipeline groups, hidden without permission).

### Step 9 — Dashboard tab + widgets (D4, D8, D9)
- `app/Filament/Pages/Dashboard.php`: prepend to `TABS` `'calendar' => ['icon' => Heroicon::OutlinedCalendarDays, 'widgets' => [CalendarGridWidget::class, CalendarDayWidget::class], 'columns' => 10, 'spans' => [7, 3]]` (extend the tab rendering minimally to honor an optional per-tab column/span map instead of the fixed `Grid::make(2)`; other tabs unchanged). `getHeaderActions()` → `[CalendarActivityAction::make()]`. Register both widgets in `DashboardPanelProvider::widgets([...])`. Add `widgets.tabs.calendar` to `lang/{en,fa,fr}/resources/dashboard/strings.php`.
- `app/Filament/Widgets/CalendarGridWidget.php extends Filament\Widgets\Widget`: `protected string $view = 'filament.widgets.calendar-grid'`; public `string $anchor`, `string $selected`, `?int $ruleId`, `?string $module`. `mount()` seeds from `request()->query('cal_date')` / `cal_rule`, else today; **filters reset on each visit by construction (nothing persisted)**. Methods: `selectDate(string $iso)`, `prevMonth()`, `nextMonth()`, `goToToday()`, `jumpTo(int $year, int $month)`, `updatedRuleId()`, `updatedModule()`. Every selection/filter change dispatches `calendar-day-selected` with `date`, `ruleId`, `module`. `#[On('calendar-toggled')] calendarToggled()` re-renders (bounds recomputed under the new calendar). `#[Computed] hits()` — ONE query: `CalendarHit::visibleTo($user)->with('rule:id,name,color,type')->whereBetween('event_date', [$range->start, $range->end])->when($ruleId, …)->when($module, …)->get()` (heads-up rules past their date excluded). `#[Computed]` `cells()`, `agenda()`, `rules()` (legend: visible active rules), `rangeLabel()`, `miniMonth()`. Presenter does all date math.
- View `resources/views/filament/widgets/calendar-grid.blade.php` + partials `calendar-grid/{header,month,agenda,mini-month,legend}.blade.php` (same folder as `pipeline-stall.blade.php`; NOT `resources/views/livewire/`, NOT the landing page). Shell `<x-filament-widgets::widget><x-filament::section>`. Header: range-label button opening the mini-month/year popover (inline `x-data="{ pickerOpen:false }"`, `@click.outside`, Esc); prev/today/next (`wire:click`; chevrons flipped by locale for RTL); rule + module `<select wire:model.live>` using `fi-select-input` classes; month/agenda toggle (inline `x-data="{ view:'month' }"`, both lists server-rendered, `x-show`). Grid: `grid-cols-7` of `<button wire:key="day-{{ $iso }}" wire:click="selectDate('{{ $iso }}')" @class([...])>` raised rounded tiles with classes `cal-day`, `--today`, `--selected`, `--past`, `--overdue` (red ring + small alert icon); up to 3 dots (`style="--cal-c:{{ $color }}"`); `+N` count badge (reuse `tabBadge()` styling). Below `md` the grid is hidden and the day-grouped agenda list shows (`hidden md:grid` / `md:hidden`). Legend row = rule color chips. RTL via logical properties only. **No maximize toggle. No help modal. No "New rule" button.**
- **No new JS file** (three inline Alpine literals). If it ever grows: `resources/js/filament/calendar-grid.js` (IIFE, `FilamentAssets` `Js::make`, vite input, `filament:assets`).
- CSS: a small `.cal-*` block in `resources/css/fi-custom.css` using `--custom-*`/`--google-*` only (tints via `color-mix(in srgb, var(--custom-…) N%, transparent)`); new keyframes go in the file's existing block; then `php artisan filament:assets`. Update `stylesPattern.md`.
- `app/Filament/Widgets/CalendarDayWidget.php extends Filament\Widgets\TableWidget`: props `?string $date`, `?int $ruleId`, `?string $module`; `#[On('calendar-day-selected')] syncSelection(...)` sets them + `resetTable()`. `table()`: `CalendarHit::visibleTo($user)->with(['rule','subject.status'])->forDay($date)->when(...)`; `Group::make('subject_type')->getTitleFromRecordUsing(fn ($r) => CalendarModules::label($r->subject_type))`, alphabetical by module label, `->defaultGroup('subject_type')`; columns: `label` (item), `rule.name` (badge in rule color), `status` (subject's localized status name; `-` if none; danger "overdue" badge when `event_date < today()` and type action). `->recordUrl(fn ($r) => CalendarModules::url($r->subject))`. Bulk export action → `ExportCalendarHits` + `app/Filament/Widgets/Exports/CalendarHitExporter.php` (`write(Builder, string): int`, `ExportDefaults`). Wrap in `TableComponents::emptyState()`. **No header "New rule" action.**
- `app/Filament/Actions/CalendarActivityAction.php` — `static make(): Action` (standalone class): `->schema([Select module (viewable), Select record (searchable on the module's `SCANNABLE_IDENTIFIER`, `getSearchResultsUsing`, max 25, live), Toggle show_all ("show all" chip, live), Placeholder content → view('filament.calendar.activity', CalendarActivity::forRecord(...))])->modalSubmitAction(false)->modalWidth('4xl')`. Views `resources/views/filament/calendar/{activity,preview}.blade.php`.

### Step 10 — Languages
`lang/{en,fa,fr}/resources/calendarRule/strings.php` with groups: `general` (model labels, nav, enum labels, palette names), `form` (all fields, section titles, helper texts, every `validation_*` incl. `in`/`*.in`/`enum`/`date`), `table`, `filters`, `infolist`, `activity` (descriptions with `:rule`, `:label`, `:date`, `:old_date`, `:status`, `:lead`; events `matched|cleared|date_changed|alert_sent|rule_updated|seen`), `alerts` (title/body/actions), `calendar` (today, prev, next, month, agenda, legend, filters, empty states, overdue). Plus `widgets.tabs.calendar` ×3. Shared cross-resource keys go in `general/strings`. Word-choice parity with sibling modules (majority convention). Emoji prefix placement follows RTL/LTR. `LangKeyIntegrityTest` must pass.

### Step 11 — Docs sweep (ONCE, at the very end)
New `app/Services/Calendar/calendarPattern.md`. One-paragraph updates: `servicesPattern.md` (pointer), `widgetsPattern.md` (new first tab + span map + two widgets), `observersPattern.md` (2 observers + all-models loop), `jobsPattern.md` (5 jobs, `ShouldBeUnique` lock note, scheduler), `modelsPattern.md` (CalendarHit = derived, no UserStamps exception), `filamentPattern.md` (§1.18 Master list + the two user-approved exceptions: Master with a form/modals, sections instead of tabs), `localizationPattern.md` (activity descriptions rendered at read time), `stylesPattern.md` (`.cal-*`), `viewsPattern.md` (calendar widget bucket + Features tab `app_features.distinguishing` ×3 locales), `CLAUDE.md` index row (Services count/Master list), `tests/testPattern.md` (new test files), `tests/qa-checklist.html` module row + `tests/log/calendar-rules.md`. A serious directory without a `*Pattern.md` up its tree must get one.

---

## 5. Tests (real dev-MySQL `useMysql()` + transaction rollback per `tests/testPattern.md`; `Queue::fake()`/`Notification::fake()` with per-recipient assertions; guard caches `SmartCacheManager::invalidate('CalendarRule')` + `Cache::forget` of `calendar_columns:*`/`calendar_relations:*` in setUp AND tearDown)

Acceptance tests from the spec (each MUST exist and pass):
1. **Multi-level relation matching** — rule on a module matches through a 2-hop dotted path (e.g. PurchaseOrder → … → sellerCompany name); MorphTo excluded; undeclared/untyped relation invisible.
2. **Hit removed when condition stops matching** — flip the related column → `syncSubject`/`syncRule` deletes the hit and logs `cleared`; also on soft delete.
3. **Each lead fires once; changed date re-arms** — two `sendDue` runs the same day → one notification; changing `event_date` empties `alerts_sent` and fires again; on-day once; missed-day catch-up collapses into one.
4. **Overdue stops after 3 / when item stops matching** — fires at +1, then +7, +14, then never; stops when hit deleted; heads-up never overdue; a hit dated today is not overdue.
5. **No base-table permission = sees nothing** — `CalendarHit::visibleTo` empty even when the rule is shared with the user and the user has other module perms; grid counts, agenda, day table, alerts recipients and the export job all exclude it; admin sees everything.
6. **Exact duplicate blocked, near-duplicate warns** — exact fingerprint → `Halt` + persistent notification (no new row); near (same `conditions_hash`) → similar-rule placeholder shown + merge unions lead times; fingerprint canonicalization ignores builder uuids/key order.

Further required tests:
- `tests/Feature/Filament/CalendarRuleResourceTest.php` — gating by `calendar_rule.view`; slide-over create persists tree + derived `related_models`; subject options exclude unviewable modules; shared-user validation gated by `{prefix}.view`; owner/admin-only edit (assert `getEditAuthorizationResponse`/`getUpdateAuthorizationResponse`, not HTML); duplicate action creates an owned `me` copy; palette `Rule::in`; preview count + ≤5 cap; observer dispatches `SyncCalendarRule` on save; `Livewire::withQueryParams(['action' => 'create'])->test(ManageCalendarRules::class)->assertActionMounted('create')`.
- `tests/Feature/Models/CalendarRuleModelTest.php`, `CalendarHitModelTest.php` — scopes, `isEditableBy`, `recipients()` permission filter, unique index positive control via `Schema::getIndexes`, enum casts.
- `tests/Feature/Services/Calendar/CalendarEngineTest.php` (subsystem master) + `CalendarAlertsTest.php` — as above plus preview cap, `touch()` routing dispatches the right job class (unique id asserted) and nothing for unrelated models, resolver cycle/depth/blocklist, `related_models` derivation, grouped vs single action URL.
- `tests/Feature/Jobs/{SyncCalendarRule,SyncCalendarSubject,SendCalendarAlerts,RebuildCalendarHits,ExportCalendarHits}Test.php` — `uniqueId`/`uniqueFor`, handle success + failure path; export re-applies visibility.
- `tests/Feature/Filament/CalendarGridWidgetTest.php` — Jalali session vs Gregorian (day numbers, first weekday, month label), today/selected/overdue cell classes, count badge, rule/module filters, deep-link seeding, `calendar-day-selected` dispatched, zero counts without permission, legend only visible rules, agenda markup present. `CalendarDayWidgetTest.php` — grouped by module alphabetically, row URL, `syncSelection` resets, bulk export pushes the job, forced ids of unpermitted modules dropped. Activity action test (default tier vs show-all).
- `tests/Feature/Filament/TopbarTest.php` — updated for the rules Quick-create entry.
- `tests/Feature/Config/ConfigTest.php` — `config/calendar.php` keys; every workspace subject has an identifier + edit route.
- Integrity tests that must stay green: `ResourceAuthorizationIntegrityTest`, `LangKeyIntegrityTest`, `RelationManagerIntegrityTest` (emptyState wrap), `AdaptiveDateTest` (any new date column/entry uses adaptive macros), the no-ForceDelete test.

---

## 6. Delivery protocol for the implementing agent

1. Work in the slices above, one coherent unit at a time. After each unit, report only: files created/changed + the targeted test result. Do not run the full suite until the end.
2. Do NOT edit docs per-edit; the single docs sweep is Step 11 (but DO read the governing pattern doc before editing in its domain).
3. If any required fact in §2 is wrong in the repo (vendor signature, route name, config shape), STOP and report it rather than inventing a workaround.
4. Anything not covered here that needs a product decision: stop and list it; do not decide.
5. Final report format: short numbered `Step → what happens` lines, ⚠️ issues first (only if real).

---

## 7. Confidence and acceptance bar

**Pre-build confidence: ~85%** that this plan builds as written without product-level rework.
- High confidence (≥90%): schema, models, observers, jobs, alerts semantics, permission scoping, resource shape, tests.
- Medium (~75–80%): (a) dynamic `RuleBuilder` constraint list rebuilding inside a modal when subject/extra_paths change; (b) the deep-path picker UX; (c) the `?action=create` auto-mount from the topbar; (d) cross-widget Livewire event sync between the grid and the day table; (e) Jalali month math at year boundaries. These get explicit tests and a Lead review focus.

**Acceptance bar (all must be true to call it done):**
1. All 6 spec acceptance tests + the further required tests pass; the three integrity tests stay green; one full-suite run at the end shows no new failures.
2. `./vendor/bin/pint` clean; zero comments / `dd()` / temp files left behind.
3. Every unit passed a fresh `claude-reviewer` subagent pass (correctness/security, then performance/pattern-consistency); no open confirmed finding; max two fix cycles per finding.
4. Permission audit: no read path (grid, agenda, day table, export, alerts, activity modal) can reveal a record or hit of a module the user lacks `.view` on.
5. All strings present in en/fa/fr; the calendar renders correctly in both Gregorian and Jalali sessions (verified by the widget tests); all 8 locale-sensitive and RTL bits use logical properties.
6. Docs sweep complete (Step 11), exceptions recorded.
7. Lead confidence gate ≥ **93%** at delivery; below that, loop (fix → review → re-gate). Remaining uncertainty stated honestly in the final report, especially anything only verifiable visually (no browser available — ask the user for visual feedback).


## last but not least

Test the risky parts first. Have the agent run a small throwaway experiment on the three uncertain parts (rule builder inside the popup, topbar link opening the popup, calendar and day list syncing) and report before building anything else.
One permission test. A single test proving a user without access to a module sees nothing of it on any screen.

---

## 8. AUDIT CORRECTIONS (read-only consistency audit against the live project; these OVERRIDE any conflicting text above)

Items marked [USER] need a user answer before that part is built; everything else is final.

### Must-fix (would fail a test or break authorization/runtime)
1. **Integrity test** — `tests/Feature/Integrity/ResourceAuthorizationIntegrityTest.php` (`ALLOWED_OVERRIDES`, ~line 87) allows overrides of the shared permission trait only for `UserResource`. Add `'App\\Filament\\Resources\\CalendarRuleResource' => ['canEdit','canDelete','canRestore','getEditAuthorizationResponse','getUpdateAuthorizationResponse','getDeleteAuthorizationResponse','getRestoreAuthorizationResponse']` and record it in `filamentPattern.md` §1.5. Add this test to the "must stay green" list.
2. **Bulk activate/deactivate bypass** — shared `HandleActivation` does a single `whereIn()->update()` gated only by `canEditAny()`: no ownership check and no model events (no sync, no purge, no activity, no `updated_by_id`). `HandleActivationTest` pins that single-query contract, so DO NOT change the shared trait. Define local `getActivateBulkAction()/getDeactivateBulkAction()` in the CalendarRule Table trait: filter records by `isEditableBy`, update per model (so observers fire), notify with the skipped count (precedent: Target/User local overrides, `filamentPattern.md` §1.31). Do not use `HandleActivation`'s bulk actions for this resource.
3. **`is_active` column bypass** — `showIsActive()` = `ToggleColumn` with `->disabled(fn ($record) => ! static::canEdit($record))` (ownership-aware `canEdit`; Department precedent). The toggle saves through the model, so observers fire.
4. **Lazy widgets break deep links** — Filament widgets are lazy by default; a lazy `mount()` runs in the `/livewire/update` request so `request()->query('cal_date'|'cal_rule')` is empty and alert deep links land on today, and the two widgets race on `calendar-day-selected`. Set `protected static bool $isLazy = false` on BOTH widgets, and seed state with `#[Url(as: 'cal_date')] public ?string $selected` / `#[Url(as: 'cal_rule')] public ?int $ruleId` instead of `request()`.
5. **Eager load throws on 2 modules** — `ProformaInvoice` and `Custom` have no `status()` relation (only PurchaseRequest, Payment, RegisteredOrder, Shipment, PurchaseOrder, BankProfile do). `with('subject.status')` on the MorphTo raises `RelationNotFoundException`. Use `morphWith([...])` per subject class listing `status` only for the 6 that declare it. Status column shows `-` for PI. For `Custom` (3 status columns) show `-` as well (no single status) — do not invent one.
6. **`viewableBy(User)` must not use `userCan()`** — `userCan()` reads `auth()->user()` and ignores any passed user (helpers.php:155), so in queued `SendCalendarAlerts`/`ExportCalendarHits` it returns false for everyone. Implement `CalendarModules::viewableBy(User $u)` as `$u->can(Str::snake(class_basename($fqcn)).'.view')` per module. [USER] see Q-A: whether admins bypass module permission.

### Authorization/data correctness
7. **Shared-user JSON membership** — query BOTH int and string forms (`whereJsonContains` for `$u->id` and `(string) $u->id`; `filamentPattern.md` §1.31, precedent `NotificationSettingResource/Traits/Filters.php:59-62`); cast ids to int on write. Apply in `scopeVisibleTo` and `recipients()`.
8. **Touch observer hot path** — `touch()` runs on every `saved` of every model, so: memoize the routing map per request (`request()->attributes`, or a static invalidated by the rule observer) instead of calling `SmartCacheManager` each time; guard on `wasChanged()`; add a `CalendarEngine::suspended()` flag (like `NotificationDispatcher::suspended()`) that import paths use; exclude `CalendarRule`, `CalendarHit` and `Pivot` subclasses; register from an explicit const class list or a shared single scan — NOT a second `File::allFiles` scan on every request (`NotificationServiceProvider` already scans). Document that query-builder `update()`/`upsert()` paths skip events (e.g. `HandleActivation` on Company), so the 02:00 rebuild is the only catch-up.
9. **Sync jobs must not swallow failures** — for `SyncCalendarRule/SyncCalendarSubject/SendCalendarAlerts/RebuildCalendarHits` let exceptions propagate (failed_jobs, retry) or `report()` then rethrow; do NOT catch-and-return (marks success). Only `ExportCalendarHits` follows the `ExportBanks` failure-notification shape.
10. **Alert locale** — queued notifications have no recipient locale. Store translation KEYS + params in the notification `data` and render at read time (as planned for activity), or carry a per-recipient locale; do not freeze title/body in the default locale.
11. **`recipients()`** must exclude inactive users (`users.status`).

### Project conventions (be 101% consistent)
12. **Model composition** — `CalendarRule`: add `HasFactory` and General `HasScope` (`scopeActive`); General `Relationships` (creator/updater) stays unaliased, `CalendarRule\Relationships as ExclusiveRelationships` is the domain one. Drop `owner()` — use `creator()` (UserStamps sets `user_id` on create; "duplicate sets user_id = auth()->id()" is redundant). Add a `booted()` throw guard for a null owner like `NotificationSetting`. Log activity from `CalendarRuleObserver` through `CalendarActivity` (modelsPattern §8: side effects live in observers) instead of the vendor `LogsActivity` trait, OR record `LogsActivity` as a documented exception — prefer the observer. `CalendarColor` enum is the single source of the 8 palette keys; derive `Rule::in` from `cases()` and drop the duplicate list from `config/calendar.php` (config keeps only the other keys). Add `app/Models/Traits/CalendarHit/Relationships.php` (`rule`, `subject`). Move hashing helpers out of `HasVisibility` into a small `HasFingerprint` trait or the observer. Document the new enum-cast style in modelsPattern.
13. **Table/infolist/filters** (match every other Master): record actions in `ActionGroup::make([...])`, toolbar in `BulkActionGroup` with order Export, Activate, Deactivate, Delete, Restore; tail `->striped()->reorderableColumns()->defaultSort('id','desc')` + `->filtersFormColumns(n)`; columns `showCreator/showUpdater/showCreationTime/showUpdateTime` (toggled hidden; `adaptiveDateTime`); infolist entries `viewCreator/viewUpdater/viewCreatedAt/viewUpdatedAt` last with `adaptiveDateTime('M Y | D: H:i:s')->color('gray')`, plus hits count; filters `getCreatorFilter()`/`getUpdaterFilter()` (`->relationship()->searchable()->preload()`) replacing the "owner" filter, Trashed filter last; `RestoreBulkAction` ALSO `->authorizeIndividualRecords()`; `ViewAction` is `->slideOver()` like Create/Edit (verify `ViewActionFooterDefaultsTest`); Duplicate = `getDuplicateAction()` in the Table trait following `RoleResource::getDuplicateAction` (append `_copy`, reuse `form()`), gated on `canCreate()`.
14. **Navigation badge** — optional (only 4 resources have one). If added: returns null at 0, key `['user_id'=>auth()->id(),'type'=>'total_count']`, count the scoped query `CalendarRule::visibleTo(...)->count()` (not `getEloquentQuery()` — it includes trashed), `getNavigationBadgeColor()` = 'info', invalidated by the observer via `SmartCacheManager::invalidate('CalendarRule')`.
15. **Lang** — add an `export` group (column labels) and separate top-level groups for enum labels (match `notificationSetting`/`target`: groups like `status`, not under `general`); drop the unneeded `date` validation key; message keys by field type: Select with options array → `in`; Select with enum options → `enum`; ToggleButtons → `in`; multiple selects → `*.in`; name them for subject, date_path, color, visibility, shared users, and the lead-time Repeater items; keep `general.model_label/plural_model_label` (PermissionLabeler). Update `localizationPattern.md` for the new groups (`activity`, `alerts`, `calendar`). `LangKeyIntegrityTest` scans only literal keys under `app/Filament`; ADD a dedicated en/fa/fr key-existence test for keys used in `app/Services/Calendar`, `app/Notifications`, blades and dynamic keys.
16. **Widgets** — existing widgets are thin wrappers calling one service method: move the `hits()` query into a service method (`CalendarEngine` or a small query class). Add the metric-legend footer convention (`HasMetricLegend`/`<x-metric-legend>`, keys `widgets.legend.calendar.{what,data,why,technical}` x3 locales) or record an explicit exception. Widget chrome strings go in `resources/dashboard/strings.widgets.calendar.*`, not `calendarRule/strings.calendar`. `CalendarDayWidget` is the FIRST `TableWidget` in the project: document it in `widgetsPattern.md`; wrap its table in `TableComponents::emptyState(...)`.
17. **Topbar entry** — use `$rules::getNavigationLabel()` and `getNavigationIcon()` like the other items; DROP the new `topbar.quick_create_calendar_rule` key. Keep gate `canCreate() && canViewAny()`, url `getUrl('index', ['action' => 'create'])`, added after the `foreach ($resources ...)` loop.
18. **Exporter** — Master-style exporters are plain `write()` classes, NOT `ExportDefaults` (`filamentPattern.md` §1.8b). Drop `ExportDefaults` from `CalendarHitExporter`. Location: follow the Master convention `Master/CalendarRuleResource/Exports/CalendarHitExporter.php` (not a new `Widgets/Exports/` folder). [USER] see Q-B for rule export.
19. **`CalendarModules::all()`** — `config/workspace.php` has only `model, route, title, subtitle, search`; derive `identifier` from `Model::SCANNABLE_IDENTIFIER` (all 8 define it); do NOT add keys to workspace.php (bound to Livewire `$modules` ids); module labels via `PermissionLabeler::getEntityLabel($fqcn)`.
20. **Notification shape** — use the project's database payload array form (`format: filament`, `shouldMarkAsRead` as in `ModelEventNotification`), not an ad-hoc builder; keep the "Mark as seen" action.
21. **Misc** — seeder rows inserted alphabetically after `bank_profile.*`; global-search methods (`getGloballySearchableAttributes` etc.) are optional — skip and say so in docs; `--cal-color-*` is a sanctioned token-family extension (state it in `stylesPattern.md`); keep the palette key `slate` but name the CSS vars `--cal-color-*` only.
22. **Tests** — one MASTER file per subsystem: `tests/Feature/Services/Calendar/CalendarSubsystemTest.php` (engine, resolver, range, presenter, activity, modules, alerts); add `tests/Feature/Notifications/CalendarAlertNotificationTest.php`; widget tests get a new row in `testPattern.md` (no precedent layer); update the `ConfigTest` docblock + Config row for `calendar.php`; `test_every_module_prefix_has_the_full_action_set_seeded` reads the dev DB, so the `Permission::firstOrCreate` repair on the dev DB is the part it checks (the seeder edit is not).
23. **More integrity tests that must stay green:** `RelationManagerIntegrityTest` (emptyState regex `return TableComponents::emptyState($table`, and no string anywhere in `app/Filament` may contain "ForceDeleteAction"), `AdaptiveDateTest` (use `adaptiveDate/adaptiveDateTime` in `*Table.php`/`*Infolist.php`), `SoftDeleteUniquenessTest` (no `->unique()` on this SoftDeletes model without `withoutTrashed()`; fingerprint uniqueness stays app-level), `ViewActionFooterDefaultsTest`, `HandleActivationTest` (unchanged — shared trait untouched), `LangKeyIntegrityTest`.
24. **Migrations** [USER] see Q-C: `migrate` runs against the shared dev MySQL (also the test DB) and the activitylog vendor migrations get published too. `testPattern.md:11` "zero active migrations" is stale (7 active files) — fix in the docs sweep.

### Plan additions from the user (also at the end of section 7 above)
- **Probe first**: before building, the implementing agent runs throwaway probes for (1) RuleBuilder in the modal — PASSED, (2) topbar `?action=create` — PASSED (visual open still to be confirmed live), (3) calendar and day-list syncing — STILL TO RUN and report; delete probe files the same turn.
- **One permission test**: `tests/Feature/Services/Calendar/CalendarPermissionTest.php` — a single test proving a user without `.view` on a module sees nothing of it on any screen (grid counts, agenda, day table, export, alerts, activity modal, rule-form module/user options, deep-link URLs). Keep the per-layer tests too.


---

## 9. RESOLVED USER DECISIONS ON THE AUDIT (final; override earlier text)

- **Q-A — No admin bypass on module permission.** Module `.view` applies to EVERYONE, admins included. `CalendarHit::scopeVisibleTo(User)` ALWAYS applies `whereIn('subject_type', CalendarModules::viewableBy($u))`. Admins differ only on RULES: they see every rule in the resource list, and may edit/delete/restore any rule (`isEditableBy`). `recipients()`, the grid, agenda, day table, export job, activity modal and alert links all respect the module permission for admins too. This overrides the earlier lines "admin => all modules" and "admin: rule visibility only" in sections 4 and 7, and the "Admins see everything" reading of the spec. The `CalendarPermissionTest` must include an admin whose role lacks one module's `.view` and prove they see nothing of it.
- **Q-B — Rule export: YES, using the app's own custom export.** Add an Export bulk action to the rules table in the conventional first position (Export, Activate, Deactivate, Delete, Restore), implemented as the project's custom plain `write()` exporter (the Master-style exporter convention, same shape as `NotificationSettingExporter`), NOT Filament's `Exporter`/`ExportDefaults`. Location `Master/CalendarRuleResource/Exports/CalendarRuleExporter.php` plus an export job that mirrors `ExportBanks`, plus an `export` lang group x3 locales. This reverses the earlier "No rule export (cut)" lines. The day-table hit export (`ExportCalendarHits` + `CalendarHitExporter`) stays as planned and also uses a plain `write()` class.
- **Q-C — The implementing agent MAY run `php artisan migrate` on the dev database.** Scope: only the new tables (`calendar_rules`, `calendar_hits`, `activity_log`); it must not alter or drop existing tables, must run `php artisan migrate:status` first and report which migrations are pending (7 migration files are active in this repo; if anything unexpected is pending besides the 3 new ones, STOP and report instead of running), and must also run the `Permission::firstOrCreate` repair for the 5 `calendar_rule.*` permissions on the dev DB.


---

## 10. ADD-ON (user-requested): Landing-page "Needs attention" shortcut list — build as Step 12, AFTER steps 1-10 are reviewed and green and BEFORE step 11 (the final review and QA pass must cover this tab)

Purpose: a short, read-only table of calendar items that need attending, on the landing page, as a shortcut. It is NOT a calendar and has no rule management. The calendar itself stays on the Filament Dashboard first tab (D4).

**Content:** up to 10 rows, ordered: overdue Action-required first (oldest overdue first), then due today, then due in the next 7 days (soonest first). Row = item label (`CalendarHit.label`), module label, rule name as a colored dot/badge (`--cal-color-*`), event date via `adaptiveDate()`, and an overdue marker when the hit is overdue. Row click opens the record (`CalendarModules::url($hit->subject)`). Footer link "Open calendar" goes to the Dashboard calendar tab (`Dashboard::getUrl()`). Empty state when nothing needs attention; the whole block is hidden when the user can view none of the 8 modules.

**Permissions (same rules as everywhere):** the data comes only from `CalendarHit::visibleTo($user)` — module `.view` applies to everyone including admins; no admin bypass; rows of modules the user cannot view never render.

**Placement and convention (viewsPattern landing-page rules):** a new eager, render-only Livewire component `App\Livewire\LandingPage\Attention` (no `wire:model`, no `#[Lazy]`; plain Blade + the existing landing-page `lp-*` tokens, built on the shared `<x-accordion-header>`/`<x-empty-state>` components), view `resources/views/livewire/landing-page/attention.blade.php` (1:1 with its class). Rendered as its OWN NEW TAB on the landing page (user decision: an additional tab, not a section inside Workspace). Verify first how the existing tabs (Workspace/Workflow/Search/Features) are registered and wired in `resources/views/filament/landing-page.blade.php` and `viewsPattern.md`, then add the new tab the same way: tab button + eager render-only body, icon, translated label (`tab_*` key in all 3 locales), position after the existing tabs, no change to the other tabs' behavior. Honour `$isRtl`/`dir`, logical properties, the `calendar_type` contract through `adaptiveDate()`, and EN/FA/FR strings under `resources/dashboard/strings.landing_page.attention.*` (follow where the existing landing-page strings live — verify first).

**Data access:** one service method `CalendarEngine::attention(User $user, int $limit = 10): Collection` (query logic stays out of the component, like the widgets): `CalendarHit::visibleTo($user)->with('rule:id,name,color,type')->where(fn ($q) => $q->overdue()->orWhereBetween('event_date', [today(), today()->addDays(7)]))->orderByRaw(...)->limit($limit)`; heads-up hits past their date are excluded; use `today()` only; select only needed columns. Cache nothing at first (single indexed query on `event_date`); if it ever needs caching use `SmartCacheManager` keyed by user id and invalidate from `CalendarRuleObserver`/`CalendarEngine::syncRule` (decide at review).

**Tests (own master file addition, no new layer):** add to `tests/Feature/Livewire/` following the existing LandingPage component tests: renders ordered rows (overdue, today, next 7 days) with the cap of 10; excludes heads-up past date; user without `.view` on a module sees none of that module's rows (include an admin whose role lacks that module's `.view`); empty state; hidden when no viewable modules; row links point to the record URL and the footer link to the Dashboard. Also extend `CalendarPermissionTest` with the landing-page surface so the "single test, no leak on any screen" claim covers it.

**Docs at the end sweep:** `viewsPattern.md` (landing-page inventory row + the new tab section), `livewirePattern.md` if it lists LandingPage components, `calendarPattern.md` (Lead-owned) and the `app_features.distinguishing` lang content x3 locales (viewsPattern maintenance rule) if this counts as a distinguishing feature.

**Acceptance:** same bar as section 7 plus: no query runs for a user with no viewable module; max 1 query for the list + eager rule load; component stays render-only; passes the landing-page integrity/lang tests.


---

## 11. PERFORMANCE HARDENING (Lead review of the observation + logging design; binding for Steps 3b, 4, 5, 9 — overrides earlier text)

### Observation (touch observer, Step 5 + engine, Step 3b)
1. **Watch columns, not just classes.** Replace the derived `related_models` list with `watched_columns` (json): `{FQCN: [column, ...]}` built at rule save from every column the filters reference + the date column + each hop's foreign keys (+ `deleted_at`). `CalendarEngine::touch($model, string $event)` returns immediately unless: the event is created/deleted/restored, OR `$model->wasChanged($watched[$class])` is true. Updating an unreferenced column (notes, description, updated_at only) must cost zero dispatches. Keep `related_models` only if still needed for display. Migration: add `watched_columns` json nullable to `calendar_rules` (edit the create migration; table is empty; apply to dev with a same-turn-erased script, never plain migrate).
2. **O(1) hot path.** The routing map (`class => rule ids + watched columns`) is built once per request/process into a static, invalidated by `CalendarRuleObserver`; no cache call per save. Skip entirely when the class is not in the map. `suspended()` flag for imports/bulk paths.
3. **Narrow related-model recompute.** A related-model change should re-evaluate only affected subject records where practical: for single-valued paths resolve affected subject ids with a reverse `whereHas`/join on the changed id (chunked), and sync only those ids via `syncSubject`. Fall back to full `syncRule` only when the reverse lookup is not expressible. Never queue a full-rule recompute per related-row save without the debounce.
4. **Dedicated queue.** Sync/alert jobs run on a named queue `calendar` (`->onQueue('calendar')`) so they never starve imports/exports; document `queue:work --queue=calendar,default`. Unique-job locks use the default cache store; note that `file` locks are filesystem operations — acceptable at current scale, switch the lock store to `database`/redis if dispatch volume grows.
5. **Engine sync.** Chunked `upsert` (500) with the date relation eager-loaded (`with($dateRelationPath)`) and the label read from the loaded model — zero per-row queries; one stale-sweep delete per run keyed by `(calendar_rule_id, synced_at)` (the unique index's leading column serves it). Wrap one rule's sync in a single transaction. Cap memory with `lazyById`/`chunkById`, never `get()`.

### Logging (activity log, Step 3b)
6. **Bulk-write activity rows.** Do NOT create one Eloquent model + event per row through `activity()->log()` inside sync loops. Collect the rows for a chunk and write them with one `insert()` per chunk (same table, `log_name = 'calendar'`, subject morph, readable description key + props JSON, timestamps). Single-event paths (one alert, one rule edit) may use the helper.
7. **Volume control (logs are kept forever).** The first sync of a new or edited rule can match thousands of records: log `matched` only for records whose match is NEW to the rule; on a rule's initial backfill write the per-record rows via the bulk insert but do not re-log unchanged matches on later syncs (log only transitions: matched, cleared, date_changed, alert_sent). Never log "still matches".
8. **Index for the history query.** The activity modal reads `log_name = 'calendar' AND subject_type = ? AND subject_id = ? ORDER BY created_at DESC LIMIT n`. Add a composite index `['subject_type', 'subject_id', 'created_at']` to the single merged `activity_log` migration (named `idx_activity_log_subject_created`), and apply it to dev with a same-turn-erased script. Paginate/limit the modal list (max 100 rows, "load more").
9. **Growth plan.** Document in `calendarPattern.md` that the table grows unbounded by design; add a note on how to archive by `created_at` later. No partitioning now (the package PK cannot include `created_at`).

### Read side (Step 9 widgets, Step 3b engine query methods)
10. **Grid = grouped counts, not all rows.** The month grid must NOT load every hit of the month. Use one grouped query: `select event_date, calendar_rule_id, count(*) c, ... group by event_date, calendar_rule_id` (+ an overdue flag via the rule type join) limited by `visibleTo`; fold into cells in PHP (rows ≤ days × rules). The agenda and day table load actual rows, paginated (day table is a Filament table — normal pagination).
11. **Replace the correlated EXISTS.** In `CalendarHit::scopeVisibleTo`, compute the visible rule ids once per request (rules table is small) and use `whereIn('calendar_rule_id', $ids)` instead of `whereHas('rule', ...)`; memoize the id list per user per request. Keep behavior identical (module `.view` always applies, no admin bypass).
12. **Hit indexes.** Keep the unique `(calendar_rule_id, subject_type, subject_id)` and `event_date`; add `(calendar_rule_id, event_date)` (per-rule filter + day views) and `(event_date, subject_type)` (grid with the module permission filter). Add them to the `calendar_hits` create migration (empty table) and apply to dev with a script. Keep indexes minimal — each extra index slows the upsert path; do not add more without a query that needs it.
13. **Alerts.** `SendCalendarAlerts` fans out one queued job per active rule (`SendCalendarRuleAlerts(ruleId)`) instead of one long loop; resolve recipients once per rule; send with `Notification::send($recipients, ...)`; mark `alerts_sent` with one bulk update per rule. A failed rule must not block the others (per-rule job isolation).
14. **No query in presenters/views.** Presenter stays pure; widgets expose computed properties (one query each); no per-cell or per-row queries; legend rules come from one query.

### Tests to add with these changes
- touch(): unwatched column update dispatches nothing; watched column / create / delete / restore dispatches; unrelated class dispatches nothing; `suspended()` dispatches nothing.
- Engine: a sync over N records issues a constant number of queries (assert with `DB::getQueryLog()` / `assertQueryCount`-style counter, not proportional to N); activity rows written via bulk insert (row counts correct, no model events fired).
- Grid query returns grouped counts with a bounded row count and the same totals as an ungrouped count.
- `scopeVisibleTo` with the id-list form returns the same rows as before for me/everyone/users/stale-list/admin cases.


---

## 12. PART 3B REVIEW — REQUIRED FIXES (binding; do these BEFORE the 4-6 checkpoint; this section wins over sections 1-11 where they differ)

Legend: [HIGH] must fix and test now. [MED] must fix now. [LOW] fix now if small, else carry the noted step. Every fix ships with a test in the same turn. Schema changes (if any) go in the migration file and are applied to dev with a small script or a `--path` migrate, never by dropping and re-creating tables silently; report each one.

### Engine (CalendarEngine)
1. [HIGH] **Stale sweep must not rely on timestamps.** `synced_at` is second-precision, so a prior run in the same second survives the sweep. Replace it: collect the matched subject ids during the scan (ints only), then delete the rule's hits whose `subject_id` is not in the matched set (compare against `pluck('subject_id')` of existing hits, delete the difference in chunks of 500; log `cleared` for each deleted hit via the bulk activity insert). Keep `synced_at` only as informational if still useful. Tests: a `syncRule` where a record stops matching deletes its hit; works with frozen time (the test file uses `setTestNow`); a second sync in the same second still deletes.
2. [HIGH] **Invalid or unknown filter leaves must never mean "match everything".** In `syncRule` and `preview`, if any leaf `type` is not among `constraintsForRule($rule)`/`constraintsFor(...)` names, or an operator is unknown, treat the rule as INVALID: purge its hits, do not alert, and report once (log + a `rule_invalid` activity row, not one per record). Tests: renamed/blocklisted column, unknown operator, missing type, empty rules (an intentionally empty rule list may match all — decide by the plan: an empty tree means "all records of the module"; keep that but make it explicit and tested).
3. [MED] **`preview()` hardening.** Signature gains the acting user: `preview(User $user, string $subject, array $filters, array $extraPaths, string $datePath, int $shift): array`. Require `$subject` in `CalendarModules::viewableBy($user)` (no counts or labels of modules the user cannot view, admins included). Validate the payload shape (rules is an array of arrays, each leaf has a string `type`; `date_path` must pass `validatePath`/be in `datePathOptions`; empty/invalid date_path rejected) and surface failures as `ValidationException`, never a 500. Tests with hostile payloads (string leaf, missing type, rules as string, bad and empty date_path, unviewable module).
4. [MED] **Related-row deletes must route.** The reverse lookup in `touch()` (`whereHas($relationPath, fn => whereKey($id))`) must use `withoutGlobalScopes()` inside the callback so a soft-deleted related row still finds its subjects. Test: soft-delete a related Company/User row narrows to the right subjects.
5. [MED] **Routing map must refresh across processes.** A long-lived `queue:work` keeps a stale static map. Add a cached version stamp (`Cache::get('calendar_routes_version')`, bumped by `CalendarRuleObserver` on save/delete/restore and by `flushRoutes()`); `touch()` compares the stamp (one cache read per save) and rebuilds the static when it changed. Test the rebuild-on-version-change.
6. [MED] **`touch()` must stay cheap.** Lower `RELATED_SUBJECT_CAP` to 50 and above it dispatch ONE debounced `SyncCalendarRule` (never hundreds of per-record jobs inside the web request). `suspended()` must save and restore the previous value (nested suspension). Confirm to-many leaf paths (HasMany/BelongsToMany as the last relation) route correctly: the related class's create/delete must be in the watched map; add the test or fix `computeWatchedColumns`.
7. [MED] **Unknown or removed subject class and trashed rules.** `syncRule`/`SyncCalendarRule` must treat a rule whose subject is not in `CalendarModules::all()`, or a soft-deleted rule (use `withTrashed()` in the job lookup), as inactive: purge its hits. Test both.
8. [MED] **`syncRule` concurrency and memory.** Do not load all existing hits into memory: look them up per chunk (`whereIn subject_id` for the chunk's ids). Replace `storeHit` `create()` with an upsert (no unique-violation race). Take a per-rule `Cache::lock('calendar_rule_sync:{id}')` (short timeout, skip or release if held) so two direct calls cannot duplicate `matched` rows. Prefer per-chunk transactions over one transaction around the whole scan (long gap locks and deadlocks with `SyncCalendarSubject`); the id-set sweep above makes this safe.
9. [LOW] `itemLabel` stored in `calendar_hits.label` must be `Str::limit($label, 255, '')`; `eventDateFor()` must catch an unparsable value and return null (skip the record, count it, never abort the job). Test both.

### Alerts (CalendarAlerts, jobs, notification)
10. [MED] **One notification per rule per alert day** (spec D11), not per kind. Collapse lead/on-day/overdue into ONE notification per recipient per rule per day, listing the first 3 items (label + a short kind marker) and "+N more"; the title carries the rule name and total. The data payload still carries `rule_id`, `hit_ids` and key+params.
11. [MED] **Do not mark or log when there are zero recipients.** Return early before `markSent` and before writing `alert_sent` rows, so a user granted access later still receives the alert. Replace the test that asserts the old behavior.
12. [MED] **Atomic send/mark.** Send first, then in ONE DB transaction re-select the due hits with `lockForUpdate`, re-check `event_date` still equals the date used to compute the due state, then mark `alerts_sent`/`overdue_count` and insert the `alert_sent` activity rows. A re-arm that happened in between must win. Make `SendCalendarRuleAlerts` `ShouldBeUnique` per rule and day (`uniqueId = ruleId:Y-m-d`). Chunk `markSent` and `dueHitsFor` in groups of 500 (placeholder and packet limits; historic backlogs).
13. [MED] **Queue wiring.** `composer.json` dev script: `queue:listen --queue=calendar,default --tries=1` (jobs on the `calendar` queue never run otherwise). Set `$this->onQueue('calendar')` in each calendar job constructor so every dispatch site is covered. Add `public int $tries = 3` and a `backoff()` to the sync jobs (a transient failure must not be final), keeping "no swallowing try/catch".
14. [HIGH] **Notification payload vs Filament's bell.** An array title breaks `Notification::fromDatabase` (TypeError) for every recipient. Nothing may dispatch alerts until the read-time renderer exists. Land the renderer in the 4-6 checkpoint (custom `DatabaseNotifications` handling that resolves keys+params in the viewer's locale, or another approach justified in the report) and add a test that loads the bell with a stored alert in en/fa/fr. Until then keep `SendCalendarAlerts` unscheduled.
15. [LOW] `sendDue(Carbon $today)` must use its argument (pass it down) or drop the parameter and update the plan contract; no dead parameters.

### Activity
16. [LOW] `CalendarActivity::seenRows` limits to 100 before intersecting `hit_ids`: fetch/filter so recent unrelated reads cannot crowd out a record's seen rows (or document the cap in `calendarPattern.md` if SQL cannot express it on this MySQL). The vendor `subject` index is now redundant with `idx_activity_log_subject_created`; leave it (vendor schema) and mention in `calendarPattern.md`.

### Pattern cleanup (code-reviewer skill)
17. [LOW] Remove descriptive docblocks from PRIVATE methods (PHPDoc on public API only). Split methods over ~20 lines: the `applyRuleToHits` closure, `touch`, `markSent`, `computeWatchedColumns`, `byKind`.
18. [LOW] Do not touch `app/Providers/NotificationServiceProvider.php`: its pint diff is the user's own earlier uncommitted work, not part of this feature.

### Carry-forward notes (no code now)
- Heads-up past-date exclusion is NOT enforced in the engine by design; Step 9's read side must exclude heads-up hits with `event_date < today()` (add a `CalendarHit` scope and a test).
- Step 5 `CalendarRuleObserver` must purge hits on rule delete and invalidate the routing version stamp.
- Step 10 adds the `strings.activity.{event}` keys in en/fa/fr.
- The Step 4-6 checkpoint also includes: remaining jobs (`SendCalendarAlerts`, `RebuildCalendarHits`, `ExportCalendarHits`), scheduler, observers, the notification renderer + bell test.

### Missing tests to add with these fixes
Stale-sweep deletion; soft-deleted subject through `syncRule`; related-model delete/soft-delete narrowing; HasMany/BelongsToMany path routing; the 50-subject fallback to one `SyncCalendarRule`; null date and negative `day_shift` through `syncRule`; a rule with `extra_paths` and a relation `date_path` inside `syncRule`; hostile/unviewable `preview`; invalid/stale filter type; a sync spanning more than one chunk (assert the query count is constant per chunk); zero recipients then recipients added later gets the alert; send failure halfway then retry does not double-send; concurrent re-arm vs mark; one-notification-per-rule-per-day collapse; notification payload shape and single-vs-grouped URL.


---

## 13. PART 3B RE-REVIEW — SECOND FIX PASS (binding; wins over everything above; this is the LAST fix cycle for these findings, so be thorough and test each one)

Same rules as section 12: each fix ships with its own test; schema changes only through the migration file + script/`--path` migrate and reported; do not touch other modules' or the user's files.

### HIGH
1. **Sweep breaks above 65,535 matches.** `CalendarEngine::sweepUnmatched` uses `whereNotIn('subject_id', $matchedIds)` (one binding per id; MySQL fails above 65,535 placeholders). Remove every count-sized binding: iterate the rule's existing hits with `chunkById` (500), test each `subject_id` against `array_flip($matchedIds)` (`isset`), collect the stale ids per chunk, delete them in that chunk and write the `cleared` rows. Memory stays ints only. Test with a small `sync_chunk` and a matched set larger than the chunk, and assert no `whereNotIn` over the full id list is used (query-log based assertion on binding count).
2. **`syncSubject` must honor invalid rules.** `syncSubject` never runs `filterProblems`, so a stale or invalid leaf makes `QueryBuilderScope` skip the filter and a touched record produces a match-everything hit (or throws on a missing `type`). Make `ruleIsSyncable()` include `filterProblems($rule) === []` and use it from BOTH `syncRule` and `syncSubject`; an invalid rule creates no hits and logs nothing in `syncSubject`. Memoize the validation result per rule for the request/job so it is not recomputed per record. Test: `syncSubject` under an invalid rule creates no hit and writes no activity.

### MEDIUM
3. **`rule_invalid` re-logging.** Log it only when the invalid-rule purge actually deleted hits, or when the set of problems changed since the last log; not on every touch or nightly rebuild. Test: two consecutive syncs of the same invalid rule produce exactly one `rule_invalid` row.
4. **Malformed OR block in `preview`** throws a 500. `validateFilterShape` and `walkLeaves` must require `is_array($leaf['data'] ?? [])` and an array `groups` for OR blocks and throw the same `ValidationException` otherwise. Test: `{type:'or', data:'zz'}` and `{type:'or', data:{groups:'zz'}}`.
5. **`subjectIdsTouching` returns after the first path.** When a touched class is reachable by several paths (e.g. `creator.name` and `approver.name`, or a filter plus the date path), union the subject ids across ALL paths and apply the cap of 50 to the union; return null (full-rule fallback) if any path is inexpressible. Test: a class reached by two paths resyncs the subjects of both.
6. **Rule lock.** TTL 30s is shorter than a large sync: use a long TTL (900s) and refresh it per chunk (or hold it with a heartbeat). When the lock is busy, `syncRule` must return a bool/enum and the job must `release(60)` instead of reporting success, so an edit made during a running sync is not lost until 02:00. Test: lock-held path is retried (released), not dropped.
7. **`syncSubject` vs the sweep race.** A hit created by `syncSubject` after the scan passed its record gets swept (not in `$matchedIds`). In `sweepUnmatched` skip hits touched after the scan began (`updated_at >= $scanStartedAt`, captured before the scan): second precision only errs in the SAFE direction (a stale hit survives one more run, a fresh hit is never lost). Test it.

### LOW (fix now)
8. **Version stamp** (`Cache::increment` on the file store is read-then-write): bump with `Cache::forever(KEY, (string) Str::uuid())` and compare by equality; a cold cache just rebuilds.
9. `SendCalendarRuleAlerts` gets `public int $uniqueFor = 3600` (a killed worker must not hold the lock forever).
10. `CalendarAlerts::markSent`: build the CASE update with bindings instead of interpolated `json_encode` SQL (safe today, fragile).
11. **Mid-send failure double-send.** Make sending idempotent per recipient: before sending to a recipient, skip them if a `notifications` row already exists for that recipient with the same `data->rule_id` and `data->alert_date` (add `alert_date` to the payload). Then a retry after a partial send or a failed commit only sends to the missing recipients. Tests: failure at recipient k then retry sends only to k..n; failed commit then retry does not double-send.
12. **Pattern cleanup, for real this time:** remove the descriptive docblocks from the private methods named in the review (`dueLeads`, `revalidate`, `markSent`, `seenRows`, `readNotifications`, `sweepUnmatched`, `watchTerminalRelation`); split the methods that are still long — `touch`, `markSent`, `preview`, `classifyChunk`, `syncSubject`, `syncRule`, `CalendarAlertNotification::toDatabase`, `constraintsFor`, `dueHitsFor`, `validatePath` — so none exceeds ~30 lines and the business-logic ones are ~20 (extract small named private methods; behavior unchanged, tests prove it).

### Carry-forward (no code now)
- Step 5's `CalendarRuleObserver` bumps the routing version stamp (the new uuid mechanism) and purges hits on rule delete.
- Step 9's read side excludes heads-up hits with `event_date < today()` (new `CalendarHit` scope + test).
- The files `tests/log/*.md`, `tests/module-audit-playbook.md`, `tests/qa-findings.json` and the Bank/Company/Currency/Department/Target/User resource tests belong to the user's earlier audit wave, not to this feature; do not touch them.

---

## 14. LESSONS LEARNED (read before EVERY step; these mistakes were found by reviews in steps 3a, 3b and 4-6 and must not repeat)

Self-check before reporting any step: go through this list against your own diff.

1. **Unbounded work.** Never bind one parameter per id (MySQL limit 65,535), never load whole tables/collections, never recurse over a relation graph, never query inside a loop. Chunk, keep ints only in memory, test with a small chunk size.
2. **Fail closed.** Invalid, stale, unknown or malformed input means "no match / skip / purge / empty", never "match everything" and never a 500. Apply the same validity check on EVERY path (rule scan, per-record sync, preview, form, export, renderer).
3. **Storage is hostile input.** JSON read from the database or a notification payload needs is_array / is_string guards and a closed failure path (string where an array is expected, missing key, array where a string is expected, null subject). One test per boundary.
4. **Permissions live in one place per concern and are repeated at every read path** (grid, agenda, day table, export, alerts, activity, landing tab, form options, preview). Module `.view` applies to everyone, admins included. Never use `auth()` or `userCan()` in queued code: pass the User and use `$user->can()`. Re-derive visibility from the database at job start; never trust a static memo left by an earlier job in the same worker.
5. **Locks, jobs and idempotency.** A lock TTL must exceed the work and be refreshed per chunk; a busy lock retries (release), it does not drop work; `release()` consumes `tries`, so bound long jobs with `retryUntil()` + `maxExceptions`. Use `ShouldBeUniqueUntilProcessing` when edits during a run must still queue a follow-up. Any send/mark/log must be safe to retry (check before sending; mark inside a transaction with a re-check). A stale item may survive one run; a fresh item must never be lost.
6. **Caches and statics.** A static memo is per process in queue workers: flush it at the START of a job, not only in test setUp/tearDown. Cross-process routing maps need a version stamp. Cache keys include what makes them stale (id + updated_at).
7. **Eloquent traps.** `Collection::get()` / `wasChanged()` / `wasRecentlyCreated` behave surprisingly: a freshly created instance keeps `wasRecentlyCreated = true` (test with a fresh instance); `saved` fires on a no-op save; plucked/raw values are not cast (compare raw to raw); Carbon objects mutate (use `copy()`); `upsert()` and query-builder writes skip model events.
8. **Typed relations and imports.** A relation method needs a Relation return type AND the matching `use` import in that trait file: `php -l` and Pint pass, the TypeError only fires when the relation is called (it broke `RegisteredOrder`/`Shipment` links until a broader test run). After touching any shared model file, run the broader module tests, not only the calendar tests.
9. **Do not touch shared or other people's files.** No edits to shared traits, other modules, the user's own files, or schema without reporting. Run `pint --test` on explicit paths only, never `pint --dirty`. Preserve each file's line endings on Windows.
10. **Schema discipline.** Edit the migration file, apply to dev with a small script or a `--path` migrate, and REPORT it; never drop and re-create tables silently; never run a plain `php artisan migrate` (the 2026_10_03 unique-active migration must stay pending).
11. **Code style.** Zero comments; no plan section numbers in code; no docblock prose on private methods; methods at most 30 lines including braces; no dead parameters; logic moved verbatim when splitting classes. When you move or rename a class, grep the pattern docs too.
12. **Tests.** Run with `timeout 120` and `--filter`; a healthy calendar run takes under 40 s, anything over 90 s is a runaway loop (stop and report). Each fix gets its own test. A failing test is fixed at its root cause, never weakened. Delete every probe/temp file in the same turn.
13. **Report honestly.** List what you only verified by reading, every file changed, every schema change, and anything pulled forward from a later step. Do not claim done while a test is red or unrun.
14. **Verify vendor APIs before use.** Never call a Filament/Laravel method you have not seen in vendor (`listWithSummary` never existed and no test rendered the View modal). Every new modal, infolist and action gets a render test.
15. **Server-side validation uses the acting user on submit**, not only the UI picker: hidden fields and option closures run on tampered state, so sanitize before use. Every write to another record needs permission AND ownership; every "similar/duplicate" lookup is scoped by visibility; memo keys include user and record.
16. **Authorization tests ship a positive control** next to each negative test, and every export job has its own test (visibility re-applied to forged ids, formula escaping, failure path). A prefill that copies the source's visibility or sharing contradicts "copy starts private".


17. **Typed Livewire `#[Url]` properties crash on forged values** before `mount` runs: declare them `mixed` and sanitize in `mount` AND in the `updated*` hooks (the client can set any public property at any time).
18. **Never use `range(1, $n)` as a repeat count** (`range(1, 0)` has two items): use `@for` or `array_fill`. Test month edges directly: offset 0, trailing 0, Esfand 30, year rollover.
19. **Permission filtering covers metadata too** (rule names, legend chips, filter options, counts), not only rows. A helper that takes a morph relation must tolerate a null subject (deleted or soft-deleted record).
20. **Tests must really exercise what they claim:** create "foreign" records while acting as the other user (`UserStamps` overwrites `user_id` with `auth()->id()`), measure query-count claims with `DB::getQueryLog()`, and never leave `//` placeholder bodies (use `{}`).

---

## 15. CHECKPOINT 10+12 — PARALLEL SLICES AND FILE OWNERSHIP (starts only after step 9 is approved)

Two coder agents work at the same time in the same working tree. The slices are file-disjoint BY RULE: an agent edits ONLY the files it owns; a file it does not own is read-only for it. If an owned slice needs a change in a file owned by the other slice, STOP that point and report it to the Lead instead of editing.

**Slice A — Step 10, activity button** (plan sections 3 Step 9/10, 11, 12)
Owns: `app/Filament/Actions/CalendarActivityAction.php` (new), `app/Services/Calendar/CalendarActivity.php`, `resources/views/filament/calendar/activity.blade.php` (new), `app/Filament/Pages/Dashboard.php` (ONLY `getHeaderActions()` returning `[CalendarActivityAction::make()]`, no other change), `lang/{en,fa,fr}/resources/calendarRule/strings.php` (the `activity` group and any activity-modal keys), tests `tests/Feature/Filament/CalendarActivityActionTest.php` (new).
Must: module select (viewable only) -> record select (searchable on the module identifier, max 25) -> "show all" toggle -> history placeholder from `CalendarActivity::forRecord()` (default tier matched/cleared/date_changed/alert_sent; show-all adds rule edits and seen events), `modalSubmitAction(false)`, `modalWidth('4xl')`, newest first, at most 100 rows. Event descriptions rendered at READ time from `resources/calendarRule/strings.activity.{event}` in en/fa/fr (all events written so far: matched, cleared, date_changed, alert_sent, rule_updated, rule_invalid, plus seen). Permission: the record must belong to a module the user can view AND must be visible to them; a forged module/record id returns nothing. The ONLY button on the calendar tab header.

**Slice B — Step 12, landing "Needs attention" tab** (plan section 10)
Owns: `app/Livewire/LandingPage/Attention.php` (new), `resources/views/livewire/landing-page/attention.blade.php` (new), the landing page tab registration (`resources/views/filament/landing-page.blade.php` and the existing tab-shell partials, after verifying how the other tabs are wired), `CalendarEngine::attention()` in `app/Services/Calendar/Sync/CalendarEngine.php` (add ONE method + helpers, no other change), landing-tab lang keys in `lang/{en,fa,fr}/resources/dashboard/strings.php` (landing section only), tests `tests/Feature/Livewire/LandingPageAttentionTest.php` (new) and the landing `Features` tab content if the maintenance rule requires it.
Must: exactly as section 10 (up to 10 rows, overdue Action-required first, then today, then next 7 days; row = label, module, rule colour dot, date via adaptiveDate, overdue marker; row click opens the record; footer link to the Dashboard calendar tab; empty state; hidden when the user can view no module; render-only eager Livewire, no wire:model, no Lazy; one query + eager rule; today() only; heads-up past-date hits excluded).

**Shared files (nobody edits directly):** `CalendarPermissionTest.php` is NOT edited by either agent. Each slice proves its own permission rule in its own test file (A: forged ids and unviewable modules return nothing; B: a user without `.view` on a module sees none of that module's rows, including an admin whose role lacks it, with a positive control). The Lead folds both into the single `CalendarPermissionTest` during consolidation.
Docs are the Lead's. No schema changes without reporting. No edits to other modules, the services layout, or the user's own files.

**Reviewers:** two `claude-reviewer` agents in review-and-fix mode, one per slice, each limited to that slice's owned files. The Lead double-checks both and consolidates.


---

## 16. PRODUCTION CHECKLIST (used at step 11; the user restarts supervisor)

1. `php artisan migrate:status` first. Run ONLY the calendar migrations by path, one at a time: `2026_10_08_100001_create_calendar_rules_table`, `2026_10_08_100002_create_calendar_hits_table`, `2026_10_08_120624_create_activity_log_table`. The former add-column migrations (`…100003` notification_type, `…100004` shared_role_ids + notify_emails) are FOLDED into `2026_10_08_100001_create_calendar_rules_table` and deleted (2026-10-09): production runs exactly these three files. NEVER a plain `migrate` (other pending migrations — departments, status workflow, products notes, the 22 Sep index file — are the user's separate decision). The 2026-10-03 unique-active-identifier migration was DELETED on 2026-10-09 (never applied on dev; it would have enforced unique business numbers but real data holds a duplicate RO number); recoverable from git commit 852fa50 if ever wanted.
2. Permissions: add `calendar_rule.view|create|edit|delete|restore` (web guard) through the Permissions screen or a small `firstOrCreate` script; assign to roles; NEVER run the full `db:seed` on production (it creates a test admin user). No permissions for the Notification Settings module (it is open by design).
3. `php artisan optimize:clear` (Spatie permission cache) and `filament:assets` if the CSS changed.
4. Workers: nothing special (calendar jobs use the default queue; `CALENDAR_QUEUE` can isolate them later); restart supervisor after the deploy (new job code).
5. Scheduler: a cron entry running `php artisan schedule:run` every minute (07:00 alerts, 02:00 rebuild; timezone Asia/Tehran).
6. After deploy: create 4-5 starter rules for real cases, check the bell shows a calendar alert, and open the Dashboard calendar tab and the landing "Needs attention" tab.


---

## 17. ALERTS HUB (user-approved: nested menu + bell icons; the cross-links are DROPPED)

The two modules stay separate pages and separate tables: Notification Settings («اطلاعیه‌ها», event alerts) and Calendar Rules (date alerts). Two small linking improvements only:

**17.1 Nested menu inside Master Data.** One parent navigation item "Alerts" inside the existing Master Data group (`navigation_group.base`) with BOTH resources as its children.
- Parent: registered in `DashboardPanelProvider` with `->navigationItems([NavigationItem::make()...])`: label from a closure over the new lang key `resources/dashboard/strings.navigation_group.alerts` (en "Alerts", fa «هشدارها», fr "Alertes"), `->group()` equal (as a closure) to the exact string both resources return from `getNavigationGroup()` (`__('resources/dashboard/strings.navigation_group.base')`), icon `heroicon-o-bell-alert`, a sort just before the children (the children keep sort 12 and 13), NO url (Filament keeps a parent item that has child items; children match the parent by label or key inside the same group: see vendor/filament/filament/src/Navigation/NavigationManager.php).
- Children: override `public static function getNavigationParentItem(): ?string` in `NotificationSettingResource` and `CalendarRuleResource` returning the SAME translated parent label. Keep their navigation labels and icons (bell and calendar-days) unchanged.
- Tests (new `tests/Feature/Filament/AlertsHubTest.php`): the Master Data group contains one "Alerts" item whose children are exactly the two resources for a user who can see both; a user who cannot see calendar rules (no `calendar_rule.view`) still sees "Alerts" with the notification child only; a user with neither still renders the panel; the topbar Quick-create menu (`work-actions.blade.php`) and `TopbarTest` still pass.
- Check in the report, by reading only if no browser: the sidebar collapse behaviour of a parent with children together with the project's nav-dock/auto-hide toggles.

**17.2 Bell icons (calendar icon on date alerts, bell icon on event alerts).**
- Calendar side: `app/Notifications/CalendarAlertNotification.php` stores `icon` => `heroicon-o-calendar-days` and a matching `iconColor` in its payload when the Filament notification payload supports them; `app/Livewire/CalendarDatabaseNotifications.php` must pass them through (it already passes vendor-shaped data). Add a render test (en/fa/fr bell test) asserting the icon.
- Event side: NO CHANGE NEEDED (decided by the Lead after the notification review): `BaseModelEventNotification::getIcon()` already returns action-specific icons (create = plus-circle, update = pencil-square, delete = trash, default = bell), which already look different from the calendar icon and are more informative than a bare bell. Do not touch the notification classes.

**Rules:** small targeted edits only (other agents work in the same tree), no schema change, no comments in code, lang parity en/fa/fr (fa never uses «نویسه»), pint on explicit paths only, tests with `timeout 150`, no docs (the Lead writes them), delete probe files, self-check against section 14.


### 17.1b REVISED (supersedes 17.1; the nested parent item is dropped)

Why: Filament v4 resources do not read `getNavigationParentItem()` (only Pages and manually built `NavigationItem`s call `parentItem()`), so the "Alerts" parent never nested its children in the user's browser. The tests only inspected the registered structure, which is why they passed. New design, two small parts:

1. **Flat "Alerts" navigation group** replaces the nested item: remove the parent `NavigationItem` from `DashboardPanelProvider::navigationItems()` and both `getNavigationParentItem()` overrides; register `NavigationGroup::make()->label(fn () => __('resources/dashboard/strings.navigation_group.alerts'))->collapsed()` in `navigationGroups()` (placed after Master Data); both `NotificationSettingResource` and `CalendarRuleResource` return that translated label from `getNavigationGroup()` (keep their labels, bell / calendar-days icons; sorts 1 and 2 inside the group). The `navigation_group.alerts` lang key (en "Alerts", fa «هشدارها», fr "Alertes") already exists.
2. **Switcher between the two pages** (the user's "tab selector changing resources"): a top sub-navigation on both list pages with two items, "When something happens" (notification settings, bell icon) and "When a date approaches" (calendar rules, calendar-days icon), the current page highlighted, the calendar item hidden for a user who cannot view calendar rules (`CalendarRuleResource::canViewAny()`). First verify in vendor/filament/filament how a resource LIST page can render sub-navigation (`HasSubNavigation::getSubNavigation()`, `SubNavigationPosition::Top`, and whether `ManageRecords` pages render it); use it if it works. If it does not, fall back to ONE shared render-hook partial (a `PanelsRenderHook::CONTENT_START` hook limited to these two resources' pages) with the same two pill links, or two header actions that link to the other page. Lang keys x3 for the two labels (genuine translations; fa never uses «نویسه»). No cross-links, no prefill, no schema.
3. **Fallout to fix in the same change:** `work-actions.blade.php` builds the Quick-create menu from `getNavigationGroup()`, so the calendar entry now lists under "Alerts": update `TopbarTest` (group ordering/labels) and rewrite `tests/Feature/Filament/AlertsHubTest.php` (the group holds both resources in order; the switcher items, the active state and the hidden calendar item for a user without `calendar_rule.view`; the translated labels in fa/fr). Tests must assert what the user SEES (render the page / the sidebar groups from `Filament::getNavigation()`), not only registered state.


---

## 18. OPEN WORK, ISSUES AND DECISIONS (live list, updated 2026-10-08; the Lead edits this section on every change)

### A. Open work
1. **Alerts group + page switcher** (plan 17.1b): DONE and reviewed by Lead (108 tests green; browser look pending), replaced the inert nested menu with a flat "Alerts" navigation group and a top switcher between Notification Settings and Calendar Rules; then review, `TopbarTest`/`AlertsHubTest` rewrite check, docs.
2. **`CalendarPermissionTest` consolidation:** DONE (one cross-surface test added: Activity, landing tab and alert recipients obey the same module `.view` rule).
3. **Backlog status refresh:** DONE (all items marked implemented).
4. **Step 11 (LAST):** docs, Features text (en/fa/fr), QA checklist row and `tests/log/calendar-rules.md` DONE; Fable 5.1 whole-feature review DONE (fixes: backslash lang key, forged widget events, Jalali jump clamp, dead code; import now suspends per-row routing and queues one resync per active rule); email channel (in-app/email/both) built, its separate Fable 5.1 review running. Remaining: user browser check, production checklist.
5. **Related-record picker (2026-10-09):** DONE - the column-level "Add a path" is replaced by a visible multi-select of related RECORDS (`extra_paths` now stores relation paths); the builder shows own columns only plus the chosen relations' columns (`<Related> → <Field>`); legacy rules are normalised from their filter leaves at validation/sync and on edit/duplicate prefill; labels humanised. Browser check pending.

6. **Option C table picker (2026-10-09):** DONE - the Linked records picker/Advanced section is replaced by a non-stored "Table" select above the builder (module itself + tables within 2 hops, "Directly linked" / "Further away"); the "Add condition" list offers only the chosen table's fields; rows from other tables keep working; `extra_paths` is derived from the rows (legacy values honoured); every condition label is `<Module> › <Field>`. The full Table|Field|Rule custom Repeater ("option B") is NOT built - the user decides after seeing this. Browser check pending.

7. **Calendar-aware conditions, lean form (2026-10-09):** DONE - the tab-1 summary line and the tab-2 intro/sentence texts are removed (half-translated); the form stays two tabs (tab 2 = Table select, builder, preview, similar/merge). Condition date pickers follow the Gregorian/Jalali toggle (stored `Y-m-d` unchanged); infolist condition lines are localized (fa/fr vendor gaps filled in `lang/vendor/filament-query-builder`) and print dates via `adaptiveDate()`.

### B. Decisions and awareness (not blocking)
1. **Migration `2026_10_03_150000_add_unique_active_identifier_columns`: DELETED 2026-10-09** (it was never applied on dev; the dev tables only have the plain business-number indexes from the 22 Sep index migration). It would have added generated `*_active` columns with UNIQUE indexes; it broke two importer tests because a genuine duplicate RO number exists. Recoverable from git commit 852fa50. Production: run only the three calendar migrations by path, never a plain `migrate`.
2. **Notification module, for awareness:** in-app and email notifications are queued (two production workers: fine); email language = the sending process's locale; editing a rule whose tables include a module the editor cannot view fails validation until that table is removed (fail closed); `is_active = []` now means off; flat values lists are malformed (no production data existed); the model-selector cache (TTL 1 h) refreshes with `optimize:clear`.
3. **Optional design leftovers (decide later):** `CalendarEngine::attention()` could live in `Display/CalendarBoard` (read side); Activity "seen" rows are not limited to the viewer's own reads; the `rule_invalid` history text prints raw machine tokens; the static `CalendarHit::$visibleRuleIds` memo could go stale in a long-lived worker (the export job flushes it); `notPastHeadsUp` adds one `exists` subquery in the month query.
4. **Known limits (documented in `calendarPattern.md`):** raw `update()`/`upsert()` writes skip model events and are caught only by the 02:00 rebuild; re-parenting a to-many related row resyncs only the new parent until the rebuild.
5. **Ideas NOT approved (do not build unless asked):** notification recipients by role / "anyone" / external emails, more condition operators, cross-links between the two modules (dropped), a single unified "alert" form, a hub page with tabs.

### C. Housekeeping and environment
1. Stale git index entries (staged-added then deleted probe files: `storage/tmp_calendar_deltas.php`, `probe-day-widget.html`, old Tmp probe tests, `Services/Calendar/CalendarEngine.php` old path, the two removed activity_log migrations): they clear with a commit or `git rm --cached`; nothing is left on disk.
2. Occasional MySQL deadlocks (`select pr_number ... for update`) appear only while several agents run tests on the shared dev DB at once; they pass in isolation.
3. The Company Import button moved to the page header (the user's change): the user's `CompanyResourceTest` may still expect it in the table toolbar (outside this feature).

### D. Needs the user's eyes (browser; the Lead cannot render it)
Calendar tile look (raised cells, today/selected/overdue, +N badge, colour dots), mini-month jump popover, month/agenda toggle, mobile agenda below `md`, RTL toolbar chevrons, the Activity button/modal inside the calendar toolbar, the "Alerts" sidebar group and the page switcher, the topbar Quick-create entries (`?action=create`), the rule form modal (RuleBuilder, deep paths, live preview), the landing "Needs attention" tab, bell icons and alert text in en/fa/fr.

### E. Done and approved (for the record)
Steps 1-9, 10 (Activity button, now inside the calendar widget, soft-deleted records included and tagged), 12 (landing tab), the queue simplification (default queue via `CALENDAR_QUEUE`), the notification-settings pass (items 0-15 incl. per-column conditions), the Alerts bell icon for calendar alerts (event alerts keep their action-specific icons).


## 19. SESSION HANDOFF (2026-10-09; the NEWEST state — where it conflicts with section 18 or earlier text, this section wins; read it first when resuming)

### 0. RESUME READING LIST (for a new session, in this order)
1. `D:\DEV-ENV\BMS-CM\CLAUDE.md` (project rules; loads automatically).
2. This file, section 19 (you are here), then section 18 only for history.
3. `C:\Users\dv-01\.claude\projects\D--DEV-ENV-BMS-CM\memory\project_calendar_rules_build_state.md` — says where to resume and records the user's working preferences (the memory index `MEMORY.md` in the same folder loads automatically).
4. The pattern doc of the area being touched: `app/Services/Calendar/calendarPattern.md`, `app/Filament/filamentPattern.md`, `app/Models/modelsPattern.md` (§11), `lang/localizationPattern.md`, `app/Filament/Widgets/widgetsPattern.md` (dashboard), `resources/views/viewsPattern.md` (landing page).

### A. State in one line
Resource level is DONE, covered by tests (578 affected tests green at the last full run, 2026-10-09) and reviewed: the two Fable 5.1 reviews (Calendar Rules, Notification Settings) finished with their fixes applied (see their findings summarised in E). Also done after the reviews: global search convention across 23 resources (`filamentPattern.md` §1.15a, Company now conforms), in-app guides (Desk Reference) beside Create on both modules, nav group renamed «【!】 مدیریت لاگ و هشدارها ⥃» (en «【!】 Logs & Alerts», fr «【!】 Journaux & Alertes»), dashboard calendar responsive (full width on mobile, 70/30 from lg). NOT yet seen in the user's browser.

### B. What exists now (final shapes)
- **Rule form = two tabs.** Tab 1 "Rule & sharing": Name, Type, Notification channel (in-app / email / both), Module, Date column (grouped by module with plain meanings; own Created at / Updated at allowed, linked-table system dates hidden), Day shift, Lead times (free list, 0-120), Visibility (Only me / Everyone / Specific users / By roles), Shared with (users; only for Specific users), Roles (only for By roles), Outside emails (only when channel is email or both; no duplicates with the audience, max 10), Color, "Also alert on the day itself", Active. Every field has a visible hint. Tab 2 "Conditions": Table select (the module itself + direct links + links reached through a direct link, max 2 hops, groups "Directly linked" / "Further away", one table at a time, rows keep their own tables), the condition builder (3-column field picker, width 5xl, labels "Module › Field"), the preview, the similar-rule / merge block. REMOVED on purpose: tab-1 summary line, tab-2 intro line, tab-2 condition sentence, the Linked-records picker / Advanced section.
- **Conditions.** Columns with a closed set (enum, a form dropdown's options, a lookup table, yes/no, low-cardinality text) are a loaded multi-select with only "is any of / is none of" (+ empty pair when nullable); foreign keys are name selectors on ANY chosen table (label shows both Persian and English name when they exist; search matches either); name fields of pure lookup tables are not offered as free text; legacy text leaves (rule #10492) stay valid and visible; condition dates follow the Jalali/Gregorian toggle and are stored as canonical Y-m-d; OR sets have three distinct localized buttons, empty sets are pruned on save.
- **Sync timing.** New rule: queued at once. Edit: 120 s debounce. Record save: instant per-subject job. 02:00 full rebuild (safety net: raw DB writes, link-only changes). 07:00 alerts only read stored hits. A queue worker must run.
- **Alerts.** One notification per rule per person per day; channel in-app / email / both (email in the worker locale; dates Jalali for fa, Gregorian otherwise); role audience = active role members who also hold the module `.view` (no admin bypass); outside emails are mail-only, carry no Open link, are guarded per address and dropped when equal to a system user's email; no address appears in activity, grid or other users' notifications.
- **Alerts hub.** Flat "Alerts" group (Notification Settings, Calendar Rules, Correspondence) between the four stage groups and Master Data, top switcher on both pages, both modules in the "+" quick-create, no nav count badge on the two alert modules, View modal footer = Delete, Create, Edit.
- **Notification Settings.** FK value pickers list all related records by name (both names, search by either, "+" opens a Select), other columns: existing values list + "+" with a type-aware input (date picker follows the calendar setting, number, Yes/No, text), closed-set columns: translated options only, localized column names, polymorphic id columns hidden, search-driven value list, a picked date fires only when the record moves INTO that day.
- **Models/data layer.** Relations completed from the schema with inverses, `#[Indirect]` through-relations (excluded from the calendar picker), `*Exclusive` relations inside each model's Relationships trait, `PredefinedOptions`, `NameSearch`, `CalendarPathResolver` is a singleton, shared column/relation labels live in `lang/<loc>/resources/general/strings.php` (`columns`, `relations`).
- **In-app guides.** Both modules carry the Desk Reference header action beside Create (config keys `notificationSetting`, `calendarRule`; content in `lang/<loc>/deskReference/{notification_settings,calendar_rules}.php`; per-user unread highlight via `HasDeskReferenceAction`).
- **Language.** en/fa/fr key parity audit test, vendor query-builder overrides in `lang/vendor/filament-query-builder/{en,fa,fr}`, date-display audit (12 surfaces fixed, see `helpersPattern.md` §10).

- **Also in place (2026-10-09, late):** Calendar Rules list search matches name, creator, editor, the localized MODULE label and the localized CHANNEL label (custom `searchable(query:)` closures); Calendar Rules is in the Filament GLOBAL search (title '📅  {name}', URL index?search=, visible + not-deleted rules only); landing "Needs attention" tab sits right AFTER Search (before the right-aligned Features tab); one table entry per RELATION PATH (Purchase Request has both `department` and `costCenter` to the Department table and both are offered — the user's rule #10492 filtered on Department while the name belongs to a Cost Center); input priority for any column: table/FK > enum > localized string options > (boolean / low-cardinality scan) > free text, with `PredefinedOptions::LOOKUP_MODELS` the only models that get "all existing values" lists; sync of a NEW rule is immediate, edits wait 120 s; a queue worker must be running (the user's first rule showed 0 entries until a worker ran — its conditions matched no Purchase Request).

### C. Decisions the user settled (do NOT re-ask)
Calendar rule form stays two tabs; OR conditions are KEPT (made clearer, not removed); closed-set columns are Selects, never free text; roles live inside the Visibility choice (no separate "also notify roles"); outside emails are shown only for email/both and are validated against the audience; the Table box is single-select (switch between additions); queue handles all background work; new rule syncs immediately; Date column may use the module's own Created at / Updated at; names of lookup records show both languages; dates follow the calendar toggle (emails: fa = Jalali); the order of tab-1 fields is fixed by the user (do not reorder); exports and the invoice PDF keep their fixed date formats; French Gregorian month names stay as the app-wide formatter produces them.

### D. Dropped (considered and rejected — do not build)
Nested Alerts menu inside Master Data; cross-links between the two alert modules; "Me (current user)" condition; daily digest across rules; removing the unused `related_models` column (harmless leftover); one-page form / removing tab 2; option B (custom three-column Table|Field|Rule rows); tab-1 summary, tab-2 intro and sentence; the manual Linked-records picker and its Advanced section; multi-select Table box; typed free text for any closed-set column; NotificationSetting nav badge.

### E. Reviewed vs not yet reviewed
Fable 5.1 passes done: whole feature (2026-10-08), the email channel, the relation-picker redesign, and (2026-10-09) the final reviews of Calendar Rules and Notification Settings. Fixes from the last two: hidden-FK leak on linked tables, forged visibility + emails server error, per-render schema queries (memoised), raw date path in the View modal, day-level date rule at midnight, notification schema queries, localized column-values summary, FK change-line labels via NameSearch, plus form-option sources now cached per locale. Left by decision: PredefinedOptions sources cached 10 min per locale; View-modal render check still asserts entry state only (Livewire harness does not render the modal); fingerprint has no unique index (double-submit accepted); FK search lists related records by name across modules (settled); in-app change-line column names are translated at send time.

### F. Open (in this order)
1. Triage the two Fable 5.1 reports (Calendar Rules, Notification Settings) and the global-search report; fix confirmed findings; re-run the affected test classes once.
2. The user's browser check (form, Table box, OR buttons, 3-column picker, Jalali dates, notification pickers and "+" boxes, global search results, Attention tab position, right-to-left look).
3. Final doc sweep after the agents finish (pattern docs were updated by each coder; this plan and `tests/log/calendar-rules.md` are Lead-owned).
4. Next session (user's decision): the Dashboard calendar tab and the workspace / landing page.
DONE this session: migrations folded (`…100003`, `…100004` into `…100001`) and deleted; the 2026-10-03 unique-active migration deleted; the stale/failing tests fixed (fa wording now read from the lang files; job-count and calendar test classes clear real rules inside their transaction); recipients (roles inside Visibility, outside emails) built and tested; closed-set coverage audit finished.

### G. Production steps
1. Run only these three migrations by path, in order: `2026_10_08_100001_create_calendar_rules_table`, `2026_10_08_100002_create_calendar_hits_table`, `2026_10_08_120624_create_activity_log_table` (if the activity table does not exist yet); never plain `migrate`, never full `db:seed` (creates a test admin).
2. Run `grant-calendar-permissions.php` (`php artisan tinker grant-calendar-permissions.php`): creates the five `calendar_rule.*` permissions and grants them to admin_junior / admin_senior; other roles via the Role screen. Then DELETE the script from the project root.
3. `php artisan optimize:clear`, `php artisan filament:assets` (fi-custom.css changed), restart supervisor (two workers).
4. The unique-active-identifier migration no longer exists (deleted 2026-10-09); nothing to keep off production except other users' pending migrations, so still never run a plain `migrate`.

### H. Known limits (accepted)
Rows are Filament's own block (field above operator), not true three columns; a new OR block starts with two sets (vendor) — empty ones are pruned; link-only changes and raw DB writes appear at the 02:00 rebuild; one message per rule per day (no cross-rule digest); an old rule with a stale hidden date cannot be re-saved until a date column is picked; notification values outside a predefined set (or mistyped for the column type) are dropped on the next save; two tables selected in Notification Settings merge same-valued ids into one option; exports keep a fixed Jalali Y-m-d; polymorphic id columns have no picker; `bank_profiles.currency_id` now has a relation.
