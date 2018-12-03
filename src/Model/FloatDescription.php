<?php

namespace Microshard\Mysql\Model;

class FloatDescription extends FieldDescription
{
    const TYPE = 'Float';

    /**
     * @param mixed $value
     * @return bool
     */
    public function validateValue(&$value): bool
    {
        $valid = is_numeric($value);
        return ($valid) ? parent::validateValue($value) : $valid;
    }
}
