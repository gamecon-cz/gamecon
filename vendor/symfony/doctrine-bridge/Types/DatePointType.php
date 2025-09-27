<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Symfony\Component\Clock\DatePoint;

final class DatePointType extends DateTimeImmutableType
{
    public const NAME = 'date_point';

    /**
     * @param T $value
     *
     * @return (T is null ? null : DatePoint)
     *
     * @template T
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DatePoint
    {
        if (null === $value || $value instanceof DatePoint) {
            return $value;
        }

        $value = parent::convertToPHPValue($value, $platform);

        return DatePoint::createFromInterface($value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
