<?php

namespace Microshard\Mysql\Model;

class StringDescription extends FieldDescription
{
    const TYPE = 'String';

    /**
     * @var int
     */
    private $maxLength;

    /**
     * @var bool
     */
    private $autoCrop = false;

    /**
     * @param int $length
     * @param bool $autoCrop
     * @return StringDescription
     */
    public function setMaxLength(int $length, bool $autoCrop = false): StringDescription
    {
        $this->maxLength = $length;
        $this->autoCrop = $autoCrop;
        return $this;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function validateValue(&$value): bool
    {
        $valid = is_string($value);
        if ($this->maxLength && strlen($value) > $this->maxLength) {
            if ($valid &= $this->autoCrop) {
                $value = substr($value, 0, $this->maxLength);
            }
        }

        return ($valid) ? parent::validateValue($value) : $valid;
    }
}
