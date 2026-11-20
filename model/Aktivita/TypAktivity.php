<?php

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\SqlStruktura\TypAktivitySqlStruktura;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;

/**
 * Typ aktivit (programová linie)
 *
 * For Doctrine entity equivalent @see \App\Entity\ActivityType
 *
 * @method static TypAktivity|null zId($id, bool $zCache = false)
 * @method static TypAktivity[] zVsech(bool $zCache = false)
 */
class TypAktivity extends \DbObject
{

    public const TURNAJ_V_DESKOVKACH = 1;
    public const LARP                = 2;
    public const PREDNASKA           = 3;
    public const RPG                 = 4;
    public const WORKSHOP            = 5;
    public const WARGAMING           = 6;
    public const BONUS               = 7;
    public const LKD                 = 8; // legendy klubu dobrodruhů
    public const DRD                 = 9; // mistrovství v DrD
    public const EPIC                = 11;
    public const DOPROVODNY_PROGRAM  = 12;
    public const DESKOHERNA          = 13;
    public const DND                 = 14; // turnaj D&D / JaD
    // interní
    public const SYSTEMOVA   = 0;
    public const TECHNICKA   = 10; // účast na technické aktivitě => cena aktivity = bonus pro "vypravěče"
    public const BRIGADNICKA = 102; // účast na brigádnícké aktivitě => cena aktivity = výplata pro "vypravěče" (brigádníka)

    public static function zNazvu(string $nazev): ?TypAktivity
    {
        return static::zWhereRadek(static::$sloupecNazev . ' = ' . dbQv($nazev));
    }

    public static function zUrl($url = null): ?TypAktivity
    {
        if ($url === null) {
            $url = \Url::zAktualni()->cela();
        }
        return self::zWhereRadek('url_typu_mn = $1', [$url]);
    }

    public static function zViditelnych()
    {
        return self::zWhere('zobrazit_v_menu = 1');
    }

    /**
     * @param int|string $idTypuAktivity
     * @return bool
     */
    public static function jeInterniDleId($idTypuAktivity): bool
    {
        return in_array((int)$idTypuAktivity, self::interniTypy());
    }

    /**
     * @return array<int>
     */
    public static function interniTypy(): array
    {
        return [self::TECHNICKA, self::BRIGADNICKA];
    }

    /**
     * @return array<int>
     */
    public static function typyKterymNevadiSdileniMistnostiSZadnymiTypy(): array
    {
        return [self::TECHNICKA, self::BRIGADNICKA];
    }

    /**
     * @return array<int>
     */
    public static function typyKterymNevadiSdileniMistnostiSeStejnymTypem(): array
    {
        return [self::WARGAMING, self::DESKOHERNA];
    }

    protected static $tabulka      = 'akce_typy';
    protected static $pk           = 'id_typu';
    protected static $sloupecNazev = 'typ_1pmn';

    /** @var \Stranka|null|false false = ještě nenačtená, viz getStrankaO() */
    private \Stranka|null|false $strankaO = false;

    public function id(): int
    {
        return (int)parent::id();
    }

    /** Vrátí popisek bez html a názvu */
    public function bezNazvu()
    {
        return trim(strip_tags(preg_replace(
            '@<h1>[^<]+</h1>@',
            '',
            $this->oTypu(),
            1, // limit
        )));
    }

    public function nazev()
    {
        return $this->r['typ_1pmn'];
    }

    public function nastavNazev(string $novyNazev): void {
        $this->r['typ_1pmn'] = $novyNazev;
    }

    public function nazevJednotnehoCisla()
    {
        return $this->r['typ_1p'];
    }

    public function __toString()
    {
        return (string)$this->nazev();
    }

    /** Název natáhnutý z nadpisu stránky, bez nadpisu název linie. Obojí escapované pro HTML. */
    public function nazevDlouhy()
    {
        if (preg_match('@<h1>([^<]+)</h1>@', (string)$this->oTypu(), $m)) {
            return $m[1];
        }
        return htmlspecialchars((string)$this->nazev());
    }

    public function oTypu()
    {
        $s = $this->getStrankaO();
        return $s ? $s->html() : null;
    }

    /**
     * Úvodní stránka linie z CMS (`akce_typy.stranka_o`).
     */
    public function getStrankaO(): ?\Stranka
    {
        // stránka linie se ptá víckrát (příznak přeložení, nadpis, úvod), načte se jednou
        if ($this->strankaO === false) {
            $this->strankaO = \Stranka::zId($this->r['stranka_o']);
        }

        return $this->strankaO;
    }

    public function popisKratky()
    {
        return $this->r['popis_kratky'];
    }

    /**
     * Název linie pro čtenáře v jazyce stránky (nebo v předaném), bez překladu česky.
     * {@see nazev()} zůstává český, je to i klíč (párování v importu, seskupení v programu).
     */
    public function getNazevVJazyce(?Jazyk $jazyk = null): string
    {
        return $this->getTextVJazyce(TypAktivitySqlStruktura::TYP_1PMN, $jazyk);
    }

    public function getPopisKratkyVJazyce(?Jazyk $jazyk = null): string
    {
        return $this->getTextVJazyce(TypAktivitySqlStruktura::POPIS_KRATKY, $jazyk);
    }

    /**
     * Text ze sloupce `{sloupec}_en` pro angličtinu, a když chybí (NULL, prázdný), český ze `{sloupec}`.
     */
    private function getTextVJazyce(string $sloupec, ?Jazyk $jazyk): string
    {
        if (($jazyk ?? AktualniJazyk::jazyk()) === Jazyk::ANGLICTINA) {
            $anglicky = trim((string) ($this->r[$sloupec . '_en'] ?? ''));
            if ($anglicky !== '') {
                return $anglicky;
            }
        }

        return (string) $this->r[$sloupec];
    }

    public function poradi()
    {
        return $this->r['poradi'];
    }

    public function posilatMailyNedorazivsim()
    {
        return (bool)$this->r['mail_neucast'];
    }

    /** Pole stránek patřících k linii */
    public function stranky()
    {
        return \Stranka::zUrlPrefixu($this->url());
    }

    public function url(): string
    {
        return $this->r['url_typu_mn'];
    }

    /** Linie z menu webu (Aktivity) */
    public function isVMenu(): bool
    {
        return (bool)$this->r['zobrazit_v_menu'];
    }

    /** Neaktivní typ si aktivita, která ho má, může ponechat, nově ho ale dostat nemůže */
    public function isAktivni(): bool
    {
        return (int)($this->r['aktivni'] ?? 0) === 1;
    }

    public function jeBrigadnicka(): bool
    {
        return $this->id() === self::BRIGADNICKA;
    }

    public function jeInterni(): bool
    {
        return self::jeInterniDleId($this->id());
    }

    public function sdileniMistnostiJeProNiProblem(): bool
    {
        return !in_array(
            $this->id(),
            [
                ...self::typyKterymNevadiSdileniMistnostiSZadnymiTypy(),
                ...self::typyKterymNevadiSdileniMistnostiSeStejnymTypem(),
            ],
        );
    }

    public function nevadiMuSdileniMistnostiSeStejnymTypem(): bool
    {
        return in_array(
            $this->id(),
            self::typyKterymNevadiSdileniMistnostiSeStejnymTypem(),
            true,
        );
    }
}
