<?php

namespace App\Observers;

use App\Services\CodeGenerator;
use Closure;
use Illuminate\Database\Eloquent\Model;

class CodeGeneratingObserver
{
    protected static bool $duringImport = false;

    public static function duringImport(Closure $callback): mixed
    {
        $previous = static::$duringImport;
        static::$duringImport = true;

        try {
            return $callback();
        } finally {
            static::$duringImport = $previous;
        }
    }

    public function creating(Model $model): void
    {
        foreach (CodeGenerator::fieldsForModel(get_class($model)) as $field) {
            if (static::$duringImport && filled($model->{$field})) {
                continue;
            }

            $model->{$field} = CodeGenerator::generate($field);
        }
    }
}
