<?php

namespace App\Observers;

use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Model;

class CodeGeneratingObserver
{
    public function creating(Model $model): void
    {
        foreach (CodeGenerator::fieldsForModel(get_class($model)) as $field) {
            $model->{$field} = CodeGenerator::generate($field);
        }
    }
}
