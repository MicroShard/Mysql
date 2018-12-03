<?php

namespace Microshard\Mysql\Model;

class DateTimeDescription extends FieldDescription
{
    const TYPE = 'DateTime';

    /**
     * @param mixed $value
     * @return bool
     */
    public function validateValue(&$value): bool
    {
        $valid = is_string($value);
        if ($valid && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $value = $value . ' 00:00:00';
        } else {
            $valid &= preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
        }

        return $valid;
    }
}
