<?php declare(strict_types=1);

namespace Gamecon\Cas;

class DateTimeGamecon extends DateTimeCz
{

  public static function zacatekGameconu(string $gameconBeziOd = GC_BEZI_OD): DateTimeGamecon {
    static $format = 'Y-m-d H:i:s';
    $zacatekGameconu = static::createFromFormat($format, $gameconBeziOd);
    if ($zacatekGameconu) {
      /** @var DateTimeGamecon $zacatekGameconu */
      return $zacatekGameconu;
    }
    throw new \RuntimeException(sprintf("Can not create date from '%s' with expected format '%s'", $gameconBeziOd, $format));
  }

  public static function zacatekGameconuProRok(int $rok): DateTimeGamecon {
    if ($rok === (int)ROK) {
      return static::zacatekGameconu(GC_BEZI_OD);
    }
    $zacatekCervence = new static($rok . '-07-01 07:00:00');
    $poradiPrvnihoDne = $zacatekCervence->format('N');
    $poradiCtvrtka = static::poradiDne(static::CTVRTEK);
    $posunNaDalsiCtvrtek = $poradiPrvnihoDne < $poradiCtvrtka
      ? $poradiCtvrtka - $poradiPrvnihoDne
      : $poradiPrvnihoDne - $poradiCtvrtka + 1;
    $nejblizsiCtvrtek = $zacatekCervence->modify("+ $posunNaDalsiCtvrtek days");
    return $nejblizsiCtvrtek->modify('+ 2 weeks');
  }

  public static function denKolemZacatkuGameconuProRok(string $den, int $rok): DateTimeGamecon {
    $zacatekGameconu = static::zacatekGameconuProRok($rok);
    if ($den === static::CTVRTEK) {
      return $zacatekGameconu;
    }
    $poradiCtvrtka = static::poradiDne(static::CTVRTEK);
    $poradiDne = static::poradiDne($den);
    if ($poradiDne === $poradiCtvrtka) {
      return $zacatekGameconu;
    }
    $rozdilDnu = $poradiDne - $poradiCtvrtka;
    return $zacatekGameconu->modify($rozdilDnu . ' days');
  }
}
