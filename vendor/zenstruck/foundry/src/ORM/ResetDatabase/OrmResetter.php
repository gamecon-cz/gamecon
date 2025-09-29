<?php

declare(strict_types=1);

/*
 * This file is part of the zenstruck/foundry package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Foundry\ORM\ResetDatabase;

use Zenstruck\Foundry\Persistence\ResetDatabase\BeforeEachTestResetter;
use Zenstruck\Foundry\Persistence\ResetDatabase\BeforeFirstTestResetter;

/**
 * @author Nicolas PHILIPPE <nikophil@gmail.com>
 */
interface OrmResetter extends BeforeFirstTestResetter, BeforeEachTestResetter
{
}
