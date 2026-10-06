<?php

namespace Tests\Feature\Integrity;

use App\Models\RegisteredOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class RelationManagerIntegrityTest extends TestCase
{
    private const RM_GLOB = '{,Operational/,Master/}*Resource/RelationManagers/*RelationManager.php';

    public function test_pivot_relation_managers_that_delete_also_offer_detach(): void
    {
        $violations = [];

        foreach (glob(app_path('Filament/Resources/'.self::RM_GLOB), GLOB_BRACE) as $file) {
            if (! preg_match('#Resources/(?:Operational|Master)/(\w+Resource)/RelationManagers#', $file, $m)) {
                continue;
            }

            $rmClass = 'App\\Filament\\Resources\\'.str_replace(
                [app_path('Filament/Resources/'), '/', '.php'],
                ['', '\\', ''],
                $file
            );
            $ownerModel = ('App\\Filament\\Resources\\'.$m[1])::getModel();

            $relationship = new ReflectionProperty($rmClass, 'relationship');
            $relationship->setAccessible(true);
            $relation = (new $ownerModel)->{$relationship->getValue()}();

            $source = file_get_contents($file);

            if ($relation instanceof BelongsToMany
                && ! str_contains($source, 'DetachAction::make()')) {
                $violations[] = "{$rmClass} is a pivot without DetachAction — the link is not removable from this RM";
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_hub_relation_managers_are_read_only(): void
    {
        $violations = [];

        foreach (glob(app_path('Filament/Resources/{,Operational/,Master/}*Resource/RelationManagers/*RelationManager.php'), GLOB_BRACE) as $file) {
            if (! preg_match('#Resources/(?:Operational|Master)/(\w+Resource)/RelationManagers#', $file, $m)) {
                continue;
            }

            $rmClass = 'App\\Filament\\Resources\\'.str_replace(
                [app_path('Filament/Resources/'), '/', '.php'],
                ['', '\\', ''],
                $file
            );
            $ownerModel = ('App\\Filament\\Resources\\'.$m[1])::getModel();

            $relationship = new ReflectionProperty($rmClass, 'relationship');
            $relationship->setAccessible(true);
            $relation = (new $ownerModel)->{$relationship->getValue()}();

            if (! $relation instanceof BelongsTo || ! $relation->getRelated() instanceof RegisteredOrder) {
                continue;
            }

            $source = file_get_contents($file);

            if (str_contains($source, 'DeleteAction')) {
                $violations[] = "{$rmClass} offers Delete on the pipeline hub — hub records are only deleted from the RegisteredOrder module";
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_relation_managers_offering_restore_also_offer_the_trashed_filter(): void
    {
        $violations = [];

        foreach (glob(app_path('Filament/Resources/'.self::RM_GLOB), GLOB_BRACE) as $file) {
            $rmClass = 'App\\Filament\\Resources\\'.str_replace(
                [app_path('Filament/Resources/'), '/', '.php'],
                ['', '\\', ''],
                $file
            );

            $source = file_get_contents($file);

            if ((str_contains($source, 'RestoreAction::make()') || str_contains($source, 'RestoreBulkAction::make()'))
                && ! str_contains($source, 'getTrashedFilter()')) {
                $violations[] = "{$rmClass} offers Restore but has no trashed filter — trashed rows are invisible, so Restore is unreachable";
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_rm_create_actions_are_status_gated_on_status_bearing_owners(): void
    {
        $violations = [];

        foreach (glob(app_path('Filament/Resources/'.self::RM_GLOB), GLOB_BRACE) as $file) {
            if (! preg_match('#Resources/(?:Operational|Master)/(\w+Resource)/RelationManagers#', $file, $m)) {
                continue;
            }

            $ownerModel = ('App\\Filament\\Resources\\'.$m[1])::getModel();

            if (! method_exists($ownerModel, 'status')) {
                continue;
            }

            $source = file_get_contents($file);

            foreach (["Action::make('create')", 'AttachAction::make()'] as $headerAction) {
                $position = strpos($source, $headerAction);

                if ($position === false) {
                    continue;
                }

                $block = substr($source, $position, 500);

                if (! str_contains($block, '->visible(')) {
                    $violations[] = basename($file)." has an ungated {$headerAction} — the owner (".class_basename($ownerModel).') carries a status';
                }
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_rm_queries_eager_load_the_sibling_resources_relations(): void
    {
        $violations = [];

        foreach (glob(app_path('Filament/Resources/'.self::RM_GLOB), GLOB_BRACE) as $file) {
            $source = file_get_contents($file);

            if (! preg_match('/(\w+)::eagerRelations\(\)/', $source, $m)) {
                $violations[] = basename($file).' builds its query without ::eagerRelations() — reused columns lazy-load status/company relations per row (N+1)';

                continue;
            }

            $short = $m[1];

            if (! preg_match('/^use ([\w\\\\]+\\\\'.preg_quote($short, '/').');$/m', $source, $fqcn) || ! class_exists($fqcn[1])) {
                $violations[] = basename($file)." references {$short}::eagerRelations() with no matching `use` import for that exact class name — likely a typo, and this fatally errors the moment the table renders";
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_rm_relationship_names_match_declared_relation_methods(): void
    {
        $violations = [];

        foreach (glob(app_path('Filament/Resources/'.self::RM_GLOB), GLOB_BRACE) as $file) {
            if (! preg_match('#Resources/(?:Operational|Master)/(\w+Resource)/RelationManagers#', $file, $m)) {
                continue;
            }

            $rmClass = 'App\\Filament\\Resources\\'.str_replace(
                [app_path('Filament/Resources/'), '/', '.php'],
                ['', '\\', ''],
                $file
            );
            $ownerModel = ('App\\Filament\\Resources\\'.$m[1])::getModel();

            $relationship = new ReflectionProperty($rmClass, 'relationship');
            $relationship->setAccessible(true);
            $name = $relationship->getValue();

            $declared = array_map(
                fn (\ReflectionMethod $method) => $method->getName(),
                (new ReflectionClass($ownerModel))->getMethods()
            );

            if (! in_array($name, $declared, true)) {
                $violations[] = "{$rmClass} \$relationship '{$name}' does not match the model method casing — in_array() hides the mismatch but relation caching and ::with() do not";
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_no_double_nested_bulk_action_groups(): void
    {
        $violations = [];

        foreach (glob(app_path('Filament/Resources/'.self::RM_GLOB), GLOB_BRACE) as $file) {
            if (preg_match('/BulkActionGroup::make\(\[\s*BulkActionGroup::make\(/', file_get_contents($file))) {
                $violations[] = basename($file).' wraps BulkActionGroup in BulkActionGroup — one level only';
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_relation_managers_do_not_declare_dead_v3_can_properties(): void
    {
        $violations = [];

        foreach (glob(app_path('Filament/Resources/'.self::RM_GLOB), GLOB_BRACE) as $file) {
            if (preg_match('/protected bool \$can\w+ = false;/', file_get_contents($file))) {
                $violations[] = basename($file).' declares a Filament v3 `$can*` property — nothing in v4 reads it; read-only is enforced structurally (no destructive actions) + via $relatedResource';
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_rm_count_columns_have_a_matching_with_count(): void
    {
        $violations = [];

        foreach (glob(app_path('Filament/Resources/'.self::RM_GLOB), GLOB_BRACE) as $file) {
            $source = file_get_contents($file);

            if (! preg_match_all('/static::(show\w+Count)\(\)/', $source, $matches)) {
                continue;
            }

            foreach ($matches[1] as $columnMethod) {
                $relation = $this->resolveCountRelation($columnMethod);

                if ($relation === null || ! str_contains($source, "withCount('{$relation}')")) {
                    $violations[] = basename($file)." renders {$columnMethod}() without withCount('{$relation}') — the count column is blank and sorting it throws";
                }
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    private function resolveCountRelation(string $columnMethod): ?string
    {
        foreach (glob(app_path('Filament/Resources/{,Operational/,Master/}*Resource/Traits/Table.php'), GLOB_BRACE) as $trait) {
            $source = file_get_contents($trait);

            if (! preg_match('/function '.$columnMethod.'\(.*?TextColumn::make\(\'(\w+)_count\'/s', $source, $m)) {
                continue;
            }

            return str($m[1])->camel();
        }

        return null;
    }

    public function test_recipient_hydration_matches_field_value_types(): void
    {
        $violations = [];
        $expectedPluck = ['recipients_to' => 'id', 'recipients_cc' => 'name'];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Filament')));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach (file($file->getPathname()) as $line) {
                foreach ($expectedPluck as $field => $column) {
                    if (str_contains($line, "'{$field}' =")
                        && str_contains($line, 'pluck(')
                        && ! str_contains($line, "pluck('{$column}'")) {
                        $violations[] = "{$file->getPathname()} hydrates {$field} without pluck('{$column}') — Select options are ID-keyed, TagsInput is name-based";
                    }
                }
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_tables_wrap_their_return_in_a_table_components_empty_state(): void
    {
        $violations = [];

        $files = array_merge(
            glob(app_path('Filament/Resources/'.self::RM_GLOB), GLOB_BRACE),
            glob(app_path('Filament/Resources/*Resource.php'))
        );

        foreach ($files as $file) {
            $source = file_get_contents($file);

            if (! preg_match('/function table\(/', $source)) {
                continue;
            }

            if (! preg_match('/return TableComponents::(emptyState|gatedEmptyState)\(\$table/', $source)) {
                $violations[] = basename($file).' has a table() method that does not wrap its return in TableComponents::emptyState()/gatedEmptyState() — inconsistent, untranslated empty-state chrome';
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_force_delete_actions_are_not_used_anywhere(): void
    {
        $violations = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Filament')));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if (! str_contains($source, 'ForceDeleteAction') && ! str_contains($source, 'ForceDeleteBulkAction')) {
                continue;
            }

            $violations[] = "{$file->getPathname()} uses ForceDeleteAction/ForceDeleteBulkAction — permanent delete is banned app-wide by convention";
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }
}
