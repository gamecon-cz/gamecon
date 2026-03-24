<?php

namespace RectorPrefix202603\Illuminate\Contracts\Container;

use Exception;
use RectorPrefix202603\Psr\Container\ContainerExceptionInterface;
class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    //
}
