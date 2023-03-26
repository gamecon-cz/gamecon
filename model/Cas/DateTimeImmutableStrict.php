<?php

declare(strict_types=1);

namespace Gamecon\Cas;

use DateTime;
use DateTimeInterface;
use Gamecon\Cas\Exceptions\InvalidModifyFormat;

/**
 * @method static static createFromMutable(DateTime $object)
 * @method static static createFromInterface(DateTimeInterface $object)
 */
class DateTimeImmutableStrict extends \DateTimeImmutable
{
    use DateTimeCzTrait;

    public function modifyStrict(string $modifier): static {
        $modified = $this->modify($modifier);
        if (!$modified) {
            throw new InvalidModifyFormat(sprintf("Can not modify %s by '%s'", static::class, $modifier));
        }
        return $modified;
    }
}
