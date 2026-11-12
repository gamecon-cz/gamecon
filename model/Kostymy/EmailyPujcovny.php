<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Kanaly\EmailovaSablona\EmailoveSablony;
use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * E-maily půjčovny kostýmů. Znění je v šablonách (admin, Web → Texty e-mailů).
 */
final class EmailyPujcovny
{
    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private readonly NastaveniPujcovny $nastaveni,
    ) {
    }

    public function posliPotvrzeni(RezervaceKostymu $rezervace, Aktivita $aktivita, \Uzivatel $ucastnik): void
    {
        $zacatek = DateTimeCz::createFromInterface($aktivita->zacatek());
        $konec = DateTimeCz::createFromInterface($aktivita->konec());

        (new EmailoveSablony($this->systemoveNastaveni))
            ->vytvorMail(KodEmailoveSablonyEnum::KOSTYM_REZERVACE_POTVRZENA, [
                'kostym'        => $rezervace->getNazevKostymu(),
                'aktivita'      => $aktivita->nazev(),
                'cas'           => $aktivita->denCasSkutecny(),
                'vyzvednuti_od' => self::denACas((clone $zacatek)->modify(sprintf('-%d minutes', $this->nastaveni->getVydejMinutPredZacatkem()))),
                'vratit_do'     => self::denACas((clone $konec)->modify(sprintf('+%d minutes', $this->nastaveni->getVraceniMinutPoKonci()))),
                'uzaverka'      => self::denACas((clone $zacatek)->modify(sprintf('-%d hours', $this->nastaveni->getUzaverkaHodinPredZacatkem()))),
                'odkaz'         => URL_WEBU . '/kostymy/moje',
            ])
            ->adresat($ucastnik->mail())
            ->odeslat();
    }

    public function posliUvolneni(RezervaceKostymu $rezervace, Aktivita $aktivita, \Uzivatel $ucastnik): void
    {
        (new EmailoveSablony($this->systemoveNastaveni))
            ->vytvorMail(KodEmailoveSablonyEnum::KOSTYM_REZERVACE_UVOLNENA, [
                'kostym'   => $rezervace->getNazevKostymu(),
                'aktivita' => $aktivita->nazev(),
                'cas'      => $aktivita->denCasSkutecny(),
            ])
            ->adresat($ucastnik->mail())
            ->odeslat();
    }

    private static function denACas(DateTimeCz $kdy): string
    {
        return $kdy->format('l G:i');
    }
}
