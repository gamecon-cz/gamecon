<?php

/*
 * This file is part of the zenstruck/foundry package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Foundry\Utils\Rector\MethodCallToFuncCallWithObjectAsFirstParameter;

final class MethodCallToFuncCallWithObjectAsFirstParameter
{
    public function __construct(
        public readonly string $methodName,
        public readonly string $functionName,
    ) {
    }
}
