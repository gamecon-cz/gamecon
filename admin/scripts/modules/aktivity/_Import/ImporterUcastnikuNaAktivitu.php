<?php

declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use \OpenSpout\Common\Exception\IOException;

class ImporterUcastnikuNaAktivitu
{
    public const STAVY_AKTIVITY_MENITELNE_IMPORTEM = [
        StavAktivity::SYSTEMOVA,
        StavAktivity::NOVA,
        StavAktivity::PUBLIKOVANA,
        StavAktivity::PRIPRAVENA,
    ];

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
    }

    /**
     * @return array{prihlasenoCelkem: int, odhlasenoCelkem: int, varovani: array<string>, chyby: array<string>};
     */
    public function importFile(
        string    $importFile,
        \Uzivatel $importujici,
    ): array {
        if (!is_readable($importFile)) {
            throw new \Chyba('Soubor s účastníky na aktivitu neexistuje nebo ho nelze přečíst.');
        }
        $reader = new XLSXReader();
        try {
            $reader->open($importFile);
        } catch (IOException) {
            throw new \Chyba('Soubor s účastníky na aktivitu nebylo možné otevřít. Je to opravdu XLSX soubor?');
        }

        $reader->getSheetIterator()->rewind();
        $sheet = $reader->getSheetIterator()->current();
        assert($sheet instanceof SheetInterface);

        $rowIterator = $sheet->getRowIterator();
        $rowIterator->rewind();
        $row = $rowIterator->current();
        $rowIterator->next();
        if ($row === null) {
            throw new \Chyba('Soubor s účastníky na aktivitu je prázdný.');
        }
        assert($row instanceof Row);
        $hlavickaKlice = array_map('trim', $row->toArray());
        if (array_diff(['id_aktivity', 'ucastnik'], $hlavickaKlice) !== []) {
            throw new \Chyba('Soubor s účastníky na aktivitu nemá správnou hlavičku. Očekáváme sloupce "id_aktivity" a "ucastnik".');
        }
        $indexIdAktivity    = array_search('id_aktivity', $hlavickaKlice, true);
        $indexNazvuAktivity = array_search('aktivita', $hlavickaKlice, true);
        $indexUcastnika     = array_search('ucastnik', $hlavickaKlice, true);

        $rocnik = $this->systemoveNastaveni->rocnik();

        /** @var $idUcastnikuPodleAktivit array{aktivita: Aktivita, ucastnici: array<int>}[] */
        $idUcastnikuPodleAktivit = [];
        $prihlasenoCelkem        = 0;
        $poradiRadku             = 0;
        /** @var int[] $preskoceneAktivity */
        $preskoceneAktivity = [];
        $varovani           = [];
        $chyby = [];
        $odhlasenoCelkem    = 0;

        /** @var array{ucastnik: \Uzivatel, aktivita: Aktivita, radek: int}[] $ucastniciPlusAktivity */
        $ucastniciPlusAktivity = [];

        /** @var \OpenSpout\Common\Entity\Row|null $row */
        while ($rowIterator->valid()) {
            try {
                $radek = $rowIterator->current()->toArray();
                $poradiRadku++;
                $rowIterator->next();

                if ($radek === []) {
                    continue;
                }
                $idAktivity     = trim((string)($radek[$indexIdAktivity] ?? ''));
                $nazevAktivity  = trim((string)$radek[$indexNazvuAktivity] ?? '');
                $jmenoUcastnika = trim((string)$radek[$indexUcastnika] ?? '');

                if ($idAktivity === '' || !is_numeric($idAktivity)) {
                    throw new \Chyba("Na řádku $poradiRadku chybí ID aktivity.");
                }

                $aktivita = $this->dejAktivitu((int)$idAktivity);
                if ($aktivita === null) {
                    throw new \Chyba("Aktivita '$idAktivity' ('$nazevAktivity') z řádku $poradiRadku neexistuje.");
                }
                if ($aktivita->rok() !== $rocnik) {
                    throw new \Chyba("Aktivita '$idAktivity' ('$nazevAktivity') z řádku $poradiRadku není pro současný ročník.");
                }
                if (!in_array($aktivita->typId(), [TypAktivity::BRIGADNICKA, TypAktivity::TECHNICKA], true)) {
                    throw new \Chyba("Aktivita '$idAktivity' ('$nazevAktivity') z řádku $poradiRadku není brigádnická ani technická a jiným není dovoleno měnit účastníky importem.");
                }
                if (!in_array(
                    $aktivita->idStavu(),
                    self::STAVY_AKTIVITY_MENITELNE_IMPORTEM,
                    true,
                )) {
                    if (!in_array($aktivita->id(), $preskoceneAktivity)) {
                        $varovani[]           = "Aktivita '$idAktivity' ('$nazevAktivity') už je v provozu (stav '{$aktivita->stav()->nazev()}') a nelze jí měnit účastníky importem.";
                        $preskoceneAktivity[] = $aktivita->id();
                    }
                    continue;
                }
                $idUcastnikuPodleAktivit[$aktivita->id()] ??= [
                    'aktivita'  => $aktivita,
                    'ucastnici' => [],
                ];

                if ($jmenoUcastnika === '') {
                    continue;
                }
                $ucastnik = $this->dejUzivatele($jmenoUcastnika);
                if ($ucastnik === null) {
                    throw new \Chyba("Nerozpoznaný uživatel '$jmenoUcastnika' na řádku $poradiRadku.");
                }
                $ucastniciPlusAktivity[]                                 = [
                    'ucastnik' => $ucastnik,
                    'aktivita' => $aktivita,
                    'radek'    => $poradiRadku,
                ];
                $idUcastnikuPodleAktivit[$aktivita->id()]['ucastnici'][] = $ucastnik->id();
            } catch (\Chyba $chyba) {
                $varovani[] = $chyba->getMessage();
                continue;
            }
        }

        dbBegin();
        try {
            // nejdříve odhlásíme, abychom získali místo pro nová přihlášení
            foreach ($idUcastnikuPodleAktivit as $idUcastnikuPodleAktivity) {
                $aktivita        = $idUcastnikuPodleAktivity['aktivita'];
                $idUcastniku     = $idUcastnikuPodleAktivity['ucastnici'];
                $odhlasenoCelkem += $this->odhlasNeuvedeneUcastniky(
                    $idUcastniku,
                    $aktivita,
                    $importujici,
                );
            }

            foreach ($ucastniciPlusAktivity as $ucastnikPlusAktivita) {
                $ucastnik = $ucastnikPlusAktivita['ucastnik'];
                assert($ucastnik instanceof \Uzivatel);
                $aktivita = $ucastnikPlusAktivita['aktivita'];
                assert($aktivita instanceof Aktivita);
                try {
                    if ($aktivita->prihlas(
                        uzivatel: $ucastnik,
                        prihlasujici: $importujici,
                        parametry: Aktivita::INTERNI,
                        hlaskyVeTretiOsobe: true,
                    )) {
                        $prihlasenoCelkem++;
                    }
                } catch (\Chyba $chyba) {
                    throw new \Chyba(sprintf(
                        "Nepodařilo se přihlásit uživatele %s na aktivitu '%s' z řádku %d: %s",
                        $ucastnik->jmenoVolitelnyNick(),
                        $aktivita->nazev(),
                        $ucastnikPlusAktivita['radek'],
                        $chyba->getMessage(),
                    ));
                }
            }

            dbCommit();
        } catch (\Throwable $e) {
            dbRollback();
            if ($e instanceof \Chyba) {
                $chyby[] = $e->getMessage();
            } else {
                throw $e;
            }
        }

        return [
            'prihlasenoCelkem' => $prihlasenoCelkem,
            'odhlasenoCelkem'  => $odhlasenoCelkem,
            'varovani'         => $varovani,
            'chyby'            => $chyby,
        ];
    }

    /**
     * @param array<int> $idUcastniku
     */
    private function odhlasNeuvedeneUcastniky(
        array     $idUcastniku,
        Aktivita  $aktivita,
        \Uzivatel $odhlasujici,
    ): int {
        $odhlasenoCelkem     = 0;
        $prihlaseniUzivatele = $aktivita->seznamPrihlasenychNeboDorazivsich();
        foreach ($prihlaseniUzivatele as $prihlasenyUzivatel) {
            if (!in_array($prihlasenyUzivatel->id(), $idUcastniku, true)) {
                $aktivita->odhlas(
                    $prihlasenyUzivatel,
                    $odhlasujici,
                    'hromadny-import-ucastniku-na-aktivitu',
                );
                $odhlasenoCelkem++;
            }
        }

        return $odhlasenoCelkem;
    }

    private function dejAktivitu(
        int $idAktivity,
    ): ?Aktivita {
        return Aktivita::zId($idAktivity, true, $this->systemoveNastaveni);
    }

    private function dejUzivatele(string $jmenoNickEmailId): ?\Uzivatel
    {
        return \Uzivatel::zIndicii($jmenoNickEmailId);
    }
}
