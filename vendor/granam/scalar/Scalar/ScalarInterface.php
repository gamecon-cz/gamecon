<?php
namespace Granam\Scalar;

interface ScalarInterface
{

    /**
     * @return string
     */
    public function __toString();

    /**
     * @return string|int|float|bool|null
     */
    public function getValue();
}
