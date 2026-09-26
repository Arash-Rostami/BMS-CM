<?php

namespace Tests\Unit;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SoftDeleteUniqueValidationTest extends TestCase
{
    public function test_unique_form_fields_on_soft_deletable_models_exclude_trashed_rows(): void
    {
        $files = File::allFiles(app_path('Filament/Resources'));
        $checked = 0;

        foreach ($files as $file) {
            if (! str_ends_with($file->getPathname(), 'Traits'.DIRECTORY_SEPARATOR.'Form.php')) {
                continue;
            }

            $path = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

            if (! preg_match('#([A-Za-z]+)Resource[/\\\\]Traits[/\\\\]Form\.php$#', $path, $m)) {
                continue;
            }

            $model = 'App\\Models\\'.$m[1];

            if (! class_exists($model) || ! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                continue;
            }

            $contents = $file->getContents();
            $offset = 0;
            $primitiveReturnTypes = ['array', 'string', 'int', 'float', 'bool', 'void', 'mixed', 'iterable', 'self', 'static', 'object', 'callable'];

            while (($pos = strpos($contents, '->unique(', $offset)) !== false) {
                $call = $this->extractBalancedCall($contents, $pos + strlen('->unique('));
                $offset = $pos + strlen('->unique(') + strlen($call);

                $enclosingReturnType = null;
                if (preg_match_all('#function\s+\w+\([^)]*\)\s*:\s*\??([A-Za-z_\\\\]+)#', substr($contents, 0, $pos), $matches)) {
                    $enclosingReturnType = strtolower(end($matches[1]));
                }

                if (in_array($enclosingReturnType, $primitiveReturnTypes, true)) {
                    continue;
                }

                $checked++;

                if (! str_contains($call, 'withoutTrashed()') && ! str_contains($call, "whereNull('deleted_at')")) {
                    $this->fail("{$path}: a ->unique() call on soft-deletable model {$m[1]} is missing modifyRuleUsing: fn (\$rule) => \$rule->withoutTrashed() — soft-deleted rows will block new records reusing the same value.");
                }
            }
        }

        $this->assertGreaterThan(0, $checked, 'Expected to find at least one ->unique() call on a soft-deletable model to verify.');
    }

    private function extractBalancedCall(string $contents, int $start): string
    {
        $depth = 1;
        $i = $start;
        $len = strlen($contents);

        while ($i < $len && $depth > 0) {
            if ($contents[$i] === '(') {
                $depth++;
            } elseif ($contents[$i] === ')') {
                $depth--;
            }
            $i++;
        }

        return substr($contents, $start, $i - $start);
    }
}
