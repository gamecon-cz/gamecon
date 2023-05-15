<?php

namespace Gamecon\Cas;

/**
 * Datum a čas s českými názvy dnů a měsíců + další vychytávky
 * @method DateTimeCz add(\DateInterval $interval)
 * @method static DateTimeCz createFromInterface(\DateTimeInterface $object)
 */
class DateTimeCz extends \DateTime
{
    use DateTimeCzTrait;
}
