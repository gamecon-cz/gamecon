<?php declare(strict_types=1);

namespace Gamecon\Mutex;

use Gamecon\Cas\DateTimeCz;

class Mutex
{
    public static function proAktivity(): Mutex
    {
        return new static('aktivity');
    }

    /**
     * @var string
     */
    private $akce;

    public function __construct(string $akce)
    {
        $this->akce = $akce;
    }

    public function dejProPodAkci(string $podAkce): Mutex
    {
        $clone       = clone $this;
        $clone->akce = $clone->akce . '-' . $podAkce;
        return $clone;
    }

    public function cekejAZamkni(int $cekejMaxMilisekund, \DateTimeInterface $do, string $klic, int $uzivatelId = null): bool
    {
        $start           = microtime(true);
        $zbyvaMilisekund = $cekejMaxMilisekund;
        while (!($zamceno = $this->zamkni($do, $klic, $uzivatelId))) {
            $trvani          = (int)(microtime(true) - $start);
            $zbyvaMilisekund -= $trvani;
            $sleep           = min($zbyvaMilisekund, 100);
            if ($sleep <= 0) {
                break;
            }
            usleep($sleep);
        }
        return $zamceno;
    }

    public function zamkni(\DateTimeInterface $do, string $klic, int $userId = null): bool
    {
        dbQuery(<<<SQL
DELETE FROM mutex WHERE akce = $1 AND do < $2
SQL
            , [$this->akce, $this->getNow()],
        );
        if ($this->zamceno()) {
            return false;
        }
        dbQuery(<<<SQL
INSERT IGNORE INTO mutex(akce, klic, zamknul, od, do) VALUES ($1, $2, $3, $4, $5)
SQL
            , [$this->akce, $klic, $userId, $this->getNow(), $do->format(DateTimeCz::FORMAT_DB)],
        );
        return $this->zamceno();
    }

    private function getNow(): string
    {
        return (new DateTimeCz())->formatDb();
    }

    public function zamceno(): bool
    {
        return (bool)dbOneCol(<<<SQL
SELECT 1 FROM mutex WHERE akce = $1 AND do >= $2
SQL
            , [$this->akce, $this->getNow()],
        );
    }

    public function odemkni(string $klic): bool
    {
        dbQuery(<<<SQL
DELETE FROM mutex WHERE akce = $1 AND klic = $2
SQL
            , [$this->akce, $klic],
        );
        return $this->zamceno();
    }
}
