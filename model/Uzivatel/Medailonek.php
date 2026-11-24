<?php

namespace Gamecon\Uzivatel;

use DbObject;
use Gamecon\Aktivita\RocnikyAktivit;
use Gamecon\Role\Role;
use Gamecon\Uzivatel\SqlStruktura\MedailonekSqlStruktura;
use Gamecon\Web\BezpecneHtml;

/**
 * For Doctrine entity equivalent @see \App\Entity\UserBadge
 *
 * Text „o sobě“ píše organizátor v adminu i vypravěč sám v Nastavení na webu, HTML z něj proto jde jen přes
 * @see BezpecneHtml. Profil Pána jeskyně (`drd`, Mistrovství v DrD skončilo v roce 2025) píše jen organizátor
 * v adminu, jde přes markdown() jako dřív (hodnocení PJ v něm má `<div class="drdStaty">`, které by čištění zahodilo).
 *
 * @method static array<Medailonek> zVsech()
 */
class Medailonek extends DbObject
{
    protected static $tabulka = MedailonekSqlStruktura::MEDAILONKY_TABULKA;
    protected static $pk      = MedailonekSqlStruktura::ID_UZIVATELE;

    /**
     * Má uživatel veřejnou stránku vypravěče /<id>? Kdo nikdy nevedl aktivitu viditelnou na webu, letos nemá roli
     * vypravěče a nemá ani medailonek, má tam 404, jinak by šli projít všichni registrovaní.
     *
     * @param list<int>|null $rocnikySAktivitami z @see RocnikyAktivit::getSAktivitami() pro tohoto uživatele, když je
     *                                           volající už má
     */
    public static function maStrankuVypravece(
        \Uzivatel $uzivatel,
        int $letosniRocnik,
        ?array $rocnikySAktivitami = null,
    ): bool {
        // od nejlevnějšího: ročníky stránka vypravěče už má, role jsou v paměti, pak dva dotazy
        return ($rocnikySAktivitami !== null && $rocnikySAktivitami !== [])
            || $uzivatel->maRoli(Role::LETOSNI_VYPRAVEC($letosniRocnik))
            || self::zId((int) $uzivatel->id()) !== null
            || ($rocnikySAktivitami === null && RocnikyAktivit::getSAktivitami($letosniRocnik, null, (int) $uzivatel->id()) !== []);
    }

    public function drd(): string
    {
        return markdown($this->r[MedailonekSqlStruktura::DRD]);
    }

    public function oSobe(): string
    {
        return BezpecneHtml::zMarkdownu($this->r[MedailonekSqlStruktura::O_SOBE]);
    }

    /**
     * Markdown, jak ho autor napsal
     */
    public function getOSobeMarkdown(): string
    {
        return (string) $this->r[MedailonekSqlStruktura::O_SOBE];
    }

    public function idUzivatele(): int
    {
        return (int)$this->r[MedailonekSqlStruktura::ID_UZIVATELE];
    }

}
