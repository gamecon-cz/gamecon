<?php

declare(strict_types=1);

namespace Granam\String;

use Granam\Scalar\ScalarInterface;

interface StringInterface extends ScalarInterface
{
    /**
     * @return string
     */
    public function getValue(): string;
}
