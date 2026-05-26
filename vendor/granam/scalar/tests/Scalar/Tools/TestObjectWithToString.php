<?php declare(strict_types=1);

namespace Granam\Tests\Scalar\Tools;

class TestObjectWithToString
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return (string)$this->value;
    }
}
