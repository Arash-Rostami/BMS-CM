<?php

namespace App\Models\Traits\General;

trait HasReliableCodeGeneration
{
    public function save(array $options = [])
    {
        if ($this->exists || $this->getConnection()->transactionLevel() > 0) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(fn () => parent::save($options), 3);
    }
}
