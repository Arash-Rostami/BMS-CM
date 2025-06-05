<?php

namespace App\Models\Traits\Product;

trait ValueTypeEstimator
{
    public function typedAttributes(): array
    {
        $attrs = $this->getAttributeValue('attributes') ?? [];
        $pairs = [];

        if (count($attrs) === 1 && !empty($attrs[0])) {
            $trimmedAttr = trim((string)$attrs[0]);
            if (!empty($trimmedAttr)) {
                $pairs['Brand'] = $trimmedAttr;
            }
        }

        foreach ($attrs as $raw) {
            $val = trim((string)$raw);

            if ($val === '') {
                continue;
            }

            if (preg_match('/^\d+(\.\d+)?\s*%$/', $val)) {
                $pairs['Purity'] = $val;
            } elseif (preg_match('/^\d{3,4}\*\d{3,4}\*\d{1,3}$/', $val)) {
                [$l, $w, $t] = explode('*', $val);
                $pairs['Length (mm)'] = $l;
                $pairs['Width (mm)'] = $w;
                $pairs['Thickness (mm)'] = $t;
            } elseif (preg_match('/^[A-Z]{2,3}\/[A-Z]{2,3}$/i', $val)) {
                $pairs['Finish'] = strtoupper($val);
            } elseif (in_array(strtoupper($val), ['S2S', 'R3/4', 'M4'], true)) {
                $pairs['Edge Profile'] = strtoupper($val);
            } elseif (preg_match('/^\d{2,3}\*\d{2,3}$/', $val)) {
                $pairs['Size'] = $val;
            } elseif (preg_match('/^\d{2,4}mm$/i', $val)) {
                $pairs['Length (mm)'] = $val;
            } elseif (preg_match('/^\d{2,4}cm$/i', $val)) {
                $pairs['Length (cm)'] = $val;
            } elseif (preg_match('/^\d+gr$/i', $val)) {
                $pairs['Weight'] = $val;
            } elseif (preg_match('/^\d+\s*(Ream|Sheet|Box)$/i', $val)) {
                $pairs['Packaging'] = $val;
            } elseif (!array_key_exists('Brand', $pairs) && preg_match('/^[\p{L}\s\-]+$/u', $val)) {
                $pairs['Brand'] = $val;
            }
        }

        return $pairs;
    }
}
