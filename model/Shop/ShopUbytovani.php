<?php

namespace Gamecon\Shop;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Pravo;
use Uzivatel;
use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura as Sql;

class ShopUbytovani
{
    private            $mozneTypy       = []; // asoc. pole [typ] => předmět sloužící jako vzor daného typu
    private            $ubytovanPoDnech = []; // všechna ubytování

    public function __construct(
        array                               $predmety,
        private readonly Uzivatel           $ubytovany,
        private readonly Uzivatel           $objednatel,
        private readonly KontextZobrazeni   $kontextZobrazeni,
    ) {
        foreach ($predmety as $predmet) {
            $nazev = Shop::bezDne($predmet[Sql::NAZEV]);
            if ($this->maPravoZobrazitUbytovani((int)$predmet[Sql::UBYTOVANI_DEN])) {
                if (!isset($this->mozneTypy[$nazev])) {
                    $this->mozneTypy[$nazev] = $predmet;
                }
            }
            $this->ubytovanPoDnech[$predmet[Sql::UBYTOVANI_DEN]][$nazev] = $predmet;
            // else z neděle na pondělí už není veřejně nabízené ubytování https://trello.com/c/rP47BsUD/940-%C3%BApravy-p%C5%99ihl%C3%A1%C5%A1ky-mastercard-2023
        }
        $this->mozneTypy = (new RazeniTypuUbytovani())->serad($this->mozneTypy);
    }

    private function maPravoZobrazitUbytovani(int $poradiHernihoDne): bool
    {
        return $poradiHernihoDne !== DateTimeGamecon::PORADI_HERNIHO_DNE_NEDELE
               || $this->ubytovany->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_NABIZET)
               || $this->ubytovany->maPravo(Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA)
               || $this->objednatel->jeOrganizator()
               || ($this->objednatel->jeInfopultak() && $this->kontextZobrazeni === KontextZobrazeni::ADMIN);
    }

    /**
     * Vrátí, jestli uživatel pro tento shop má ubytování v kombinaci den, typ
     * @param int $den číslo dne jak je v databázi
     * @param string $typ typ ubytování ve smyslu názvu z DB bez posledního slova
     * @return bool je ubytován?
     */
    public function ubytovan(
        $den,
        $typ,
    ): bool {
        return isset($this->ubytovanPoDnech[$den][$typ])
               && $this->ubytovanPoDnech[$den][$typ]['kusu_uzivatele'] > 0;
    }

    public function veKterychDnechJeUbytovan(): array
    {
        $dnyUbytovani = [];
        foreach ($this->ubytovanPoDnech as $den => $typyADetaily) {
            foreach ($typyADetaily as /* $typUbytovani => */ $detail) {
                if ($detail['kusu_uzivatele'] > 0) {
                    $dnyUbytovani[] = $den;
                }
            }
        }

        return $dnyUbytovani;
    }

    public function maObjednaneUbytovani(): bool
    {
        return count($this->veKterychDnechJeUbytovan()) > 0;
    }

    /**
     * Názvy objednaného ubytování (typ + den), pro souhrn objednávek na infopult.
     * @return string[]
     */
    public function objednaneUbytovaniNazvy(): array
    {
        $nazvy = [];
        foreach ($this->ubytovanPoDnech as $typyADetaily) {
            foreach ($typyADetaily as $detail) {
                if (($detail['kusu_uzivatele'] ?? 0) > 0) {
                    $nazvy[] = $detail['nazev'];
                }
            }
        }

        return $nazvy;
    }

    public function kratkyPopis(string $oddelovacDalsihoRadku = '<br>'): string
    {
        $dnyPoTypech = [];
        foreach ($this->ubytovanPoDnech as $cisloDne => $typy) { // typy _v daný den_
            $typVzor = reset($typy);
            foreach ($this->mozneTypy as $typ => $rozsah) {
                if ($this->ubytovan($cisloDne, $typ)) {
                    $poziceZaPosledniMezerou = strrpos($typVzor['nazev'], ' ') + 1;
                    $nazevDne                = mb_strtolower(substr($typVzor['nazev'], $poziceZaPosledniMezerou));
                    $zkratkaDne              = mb_substr($nazevDne, 0, 2);
                    $dnyPoTypech[$typ][]     = $zkratkaDne;
                }
            }
        }
        $typySeDny = [];
        foreach ($dnyPoTypech as $typ => $dny) {
            $typySeDny[] = "$typ: " . implode(',', $dny);
        }

        return implode($oddelovacDalsihoRadku, $typySeDny);
    }
}
