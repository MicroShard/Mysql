<?php

namespace Microshard\Mysql\Model;

class BoolDescription extends FieldDescription
{
    const TYPE = 'Bool';

    /**
     * @param mixed $value
     * @return bool
     */
    public function validateValue(&$value): bool
    {
        $valid = $value === true || $value === false
            || $value == 1 || $value == 0;

        if ($valid) {
            $value = ($value) ? 1 : 0;
        }

        return $valid;
    }
}
