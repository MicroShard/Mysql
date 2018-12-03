<?php

namespace Microshard\Mysql\Model;

class IntDescription extends FieldDescription
{
    const TYPE = 'Int';

    /**
     * @var bool
     */
    private $unsigned = false;

    /**
     * @return $this
     */
    public function setUnsigned()
    {
        $this->unsigned = true;
        return $this;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function validateValue(&$value): bool
    {
        $valid = is_numeric($value);
        $valid &= ($this->unsigned) ? $value >= 0 : true;

        return ($valid) ? parent::validateValue($value) : $valid;
    }
}
