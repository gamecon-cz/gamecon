<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\XTemplate\XTemplate;

/**
 * Stránka Web → Soubory: PDF, zvuk, archivy, tabulky a obrázky ke stažení ve složkách `soubory/obsah`, na které
 * odkazují texty webu. Pevný seznam složek je v @see self::SLOZKY, nic jiného se odsud nezapíše ani nesmaže.
 */
final class SouboryWebu
{
    /**
     * Každá složka přijímá všechno, co stránka přijímá: složku si organizátor vybírá podle toho, kam soubor patří,
     * ne podle jeho typu.
     */
    public const FORMATY = [...NahravaniObrazku::DOKUMENTY, ...NahravaniObrazku::WEBOVE_OBRAZKY];

    /**
     * Složky v `soubory/obsah`, název složky => k čemu slouží. Obrázky e-shopu (`materialy/<ročník>/eshop`) mají
     * vlastní modul a tady se nevypisují. Složky s `historie` se vypisují na konci pod čárou.
     */
    public const SLOZKY = [
        'download' => [
            'nazev'   => 'Ke stažení',
            'popis'   => 'Soubory ke stažení, například tisková kronika nebo orientační brožura.',
            'formaty' => self::FORMATY,
        ],
        'materialy' => [
            'nazev'   => 'Materiály a formuláře',
            'popis'   => 'Podklady odkazované z textů stránek a z přihlášky, třeba souhlas rodičů, po ročnících v podsložkách.',
            'formaty' => self::FORMATY,
        ],
        'celohra' => [
            'nazev'   => 'Celohra',
            'popis'   => 'Podklady k celohře.',
            'formaty' => self::FORMATY,
        ],
        'drd' => [
            'nazev'    => 'Mistrovství v Dračím doupěti',
            'popis'    => 'Archiv stránky DrD, poslední ročník byl 2025: dobrodružství, deníky postav a nahrávky.',
            'historie' => true,
            'formaty'  => self::FORMATY,
        ],
    ];

    private const POST_AKCE = 'souboryWebuAkce';
    private const POST_SOUBOR = 'souborWebu';
    /** Do kolika pater podsložek se soubory vypisují, nahrávat se smí do @see self::MAX_HLOUBKA_PODSLOZEK */
    private const MAX_HLOUBKA_VYPISU = 5;
    private const MAX_HLOUBKA_PODSLOZEK = 2;

    /**
     * @var array<string, NahravaniObrazku>
     */
    private array $sluzby = [];
    private readonly FormularNahraniSouboru $formular;

    /**
     * @param array<string, array{nazev: string, popis: string, formaty: list<string>, historie?: bool}> $slozky
     * @param string|null $korenObsahu null je `soubory/obsah` webu
     * @param NahravaniPoCastech|null $poCastech s ním se velký soubor pošle po částech a formulář dostane jen jeho `id`;
     *                                           bez něj se soubor posílá celý jedním požadavkem
     */
    public function __construct(
        private readonly array $slozky = self::SLOZKY,
        private readonly ?string $korenObsahu = null,
        private readonly PouzitiObrazku $pouziti = new PouzitiObrazku(),
        private readonly ?NahravaniPoCastech $poCastech = null,
    ) {
        $this->formular = new FormularNahraniSouboru(self::POST_SOUBOR, $poCastech);
    }

    /**
     * Obslouží formuláře z @see html(). Po akci přesměruje zpět a skončí, jinak nedělá nic.
     */
    public function zpracujPost(): void
    {
        NahravaniObrazku::hlidejPrilisVelkyPozadavek();
        $akce = post(self::POST_AKCE);
        if (!in_array($akce, ['nahrat', 'nahradit', 'smazat'], true)) {
            return;
        }
        oznameni(NahravaniObrazku::sOhlasenimChyby(fn (): string => $this->formular->sUklidem(fn (): string => match ($akce) {
            'nahrat'   => $this->nahraj(),
            'nahradit' => $this->nahrad(),
            'smazat'   => $this->smaz(),
        })));
    }

    public function html(): string
    {
        $template = new XTemplate(__DIR__ . '/soubory-webu.xtpl');
        $template->assign([
            'postAkce'   => self::POST_AKCE,
            'postSoubor' => self::POST_SOUBOR,
            'poCastech'  => $this->formular->getAtributy(),
            'skript'     => $this->formular->getSkript(),
        ]);
        $odkazyVTextech = $this->pouziti->odkazyVeSlozkach(array_map(
            fn (int|string $slozka): string => $this->cestaVuciWebu((string) $slozka),
            array_keys($this->slozky),
        ));

        $predchoziJeHistorie = null;
        foreach ($this->slozkyNaKonciHistorie() as $nazevSlozky => $nastaveni) {
            // číselný název složky je v poli celé číslo
            $slozka = (string) $nazevSlozky;
            $jeHistorie = $nastaveni['historie'] ?? false;
            if ($jeHistorie && $predchoziJeHistorie === false) {
                $template->parse('souboryWebu.slozka.historie');
            }
            $predchoziJeHistorie = $jeHistorie;
            $nahravani = $this->nahravani($slozka);
            $soubory = VypisSouboru::odNejnovejsiho($nahravani, array_values(array_filter(
                $nahravani->souboryVeStromu('', self::MAX_HLOUBKA_VYPISU),
                static fn (string $soubor): bool => !ZpracovaneObrazky::jeAdresarJinehoModulu($slozka . '/' . $soubor),
            )));
            $template->assign([
                'slozka'  => htmlspecialchars($slozka),
                'nazev'   => htmlspecialchars($nastaveni['nazev']),
                'popis'   => htmlspecialchars($nastaveni['popis']),
                'adresar' => htmlspecialchars($this->cestaVuciWebu($slozka) . '/'),
                'limit'   => htmlspecialchars($nahravani->popisLimitu()),
                'accept'  => htmlspecialchars($nahravani->accept()),
                'pocet'   => count($soubory),
                'rocnik'  => ROCNIK,
            ]);

            foreach (self::podslozkyKamSeDaNahrat($soubory) as $podslozka) {
                $template->assign('podslozka', htmlspecialchars($podslozka));
                $template->parse('souboryWebu.slozka.podslozka');
            }

            foreach ($soubory as $soubor) {
                $this->vypisSoubor($template, $nahravani, $nastaveni['formaty'], $slozka, $soubor, $odkazyVTextech);
            }
            if ($soubory === []) {
                $template->parse('souboryWebu.slozka.zadny');
            }
            $template->parse('souboryWebu.slozka');
        }
        $template->parse('souboryWebu');

        return $template->text('souboryWebu');
    }

    /**
     * Aktuální složky, za nimi historické. Operátor `+` drží klíče, i číselné, které by spread přečísloval.
     *
     * @return array<string, array{nazev: string, popis: string, formaty: list<string>, historie?: bool}>
     */
    private function slozkyNaKonciHistorie(): array
    {
        return array_filter($this->slozky, static fn (array $nastaveni): bool => !($nastaveni['historie'] ?? false))
            + array_filter($this->slozky, static fn (array $nastaveni): bool => $nastaveni['historie'] ?? false);
    }

    /**
     * Podsložky, ve kterých soubory leží, bez té samotné složky: `2026/podklady/a.pdf` → `2026`, `2026/podklady`.
     * Nahrávání z názvu podsložky vyrobí @see NahravaniObrazku::bezpecnyNazevDokumentu(), takže se nabízí jen ty,
     * které by se tím nezměnily a vejdou se do povolené hloubky.
     *
     * @param list<string> $soubory
     * @return list<string>
     */
    private static function podslozkyKamSeDaNahrat(array $soubory): array
    {
        $podslozky = [];
        foreach ($soubory as $soubor) {
            $segmenty = explode('/', dirname($soubor));
            while ($segmenty !== [] && $segmenty !== ['.']) {
                if (count($segmenty) <= self::MAX_HLOUBKA_PODSLOZEK
                    && array_filter($segmenty, self::jeNazevPodslozkyBezeZmeny(...)) === $segmenty
                ) {
                    $podslozky[implode('/', $segmenty)] = true;
                }
                array_pop($segmenty);
            }
        }
        // číselná podsložka, třeba ročník, je v poli celé číslo
        $podslozky = array_map(strval(...), array_keys($podslozky));
        sort($podslozky, SORT_NATURAL | SORT_FLAG_CASE);

        return $podslozky;
    }

    private static function jeNazevPodslozkyBezeZmeny(string $segment): bool
    {
        try {
            return NahravaniObrazku::bezpecnyNazevDokumentu($segment) === $segment;
        } catch (NahravaniObrazkuException) {
            return false;
        }
    }

    /**
     * @param list<string> $povoleneFormaty
     * @param array<string, list<array{popis: string, url: string}>> $odkazyVTextech
     */
    private function vypisSoubor(
        XTemplate $template,
        NahravaniObrazku $nahravani,
        array $povoleneFormaty,
        string $slozka,
        string $soubor,
        array $odkazyVTextech,
    ): void {
        $absolutniCesta = $nahravani->cestaKSouboru($soubor);
        // z názvu složky, ne ze skutečné cesty: když je složka symlink na jiné úložiště, vede ta mimo web
        $cestaVuciWebu = $this->cestaVuciWebuSouboru($slozka, $soubor);
        $pouziti = $cestaVuciWebu === null
            ? []
            : [
                ...($odkazyVTextech[mb_strtolower($cestaVuciWebu)] ?? []),
                ...PouzitiObrazku::odkazyZKodu($cestaVuciWebu),
            ];
        $mime = NahravaniObrazku::mimePodlePripony($soubor);
        $template->assign([
            'cesta'         => htmlspecialchars($soubor),
            'url'           => htmlspecialchars((string) $nahravani->urlSouboru($absolutniCesta)),
            'udaje'         => htmlspecialchars(VypisSouboru::getUdaje($soubor, $absolutniCesta)),
            'zmeneno'       => VypisSouboru::getNahrano($absolutniCesta),
            'markdown'      => $cestaVuciWebu === null
                ? ''
                : htmlspecialchars(ZapisSouboruVTextu::markdown($cestaVuciWebu)),
            'html'          => $cestaVuciWebu === null
                ? ''
                : htmlspecialchars(ZapisSouboruVTextu::html($cestaVuciWebu)),
            'acceptSouboru' => $nahravani->acceptProNahrazeni((string) $mime),
        ]);

        if ($mime === null || !in_array($mime, $povoleneFormaty, true) || !FormularNahraniSouboru::nazevPrezijeFormular($soubor)) {
            $template->parse('souboryWebu.slozka.soubor.nespravovany');
        } else {
            $template->parse('souboryWebu.slozka.soubor.nahradit');
            if ($pouziti === []) {
                $template->parse('souboryWebu.slozka.soubor.smazat');
            }
        }
        if ($pouziti === []) {
            $template->parse('souboryWebu.slozka.soubor.nikde');
        }
        foreach ($pouziti as ['popis' => $popis, 'url' => $url]) {
            $template->assign([
                'pouzitiPopis' => htmlspecialchars($popis),
                'pouzitiUrl'   => htmlspecialchars($url),
            ]);
            $template->parse($url === ''
                ? 'souboryWebu.slozka.soubor.misto.vKodu'
                : 'souboryWebu.slozka.soubor.misto.vTextu');
            $template->parse('souboryWebu.slozka.soubor.misto');
        }
        $template->parse('souboryWebu.slozka.soubor');
    }

    private function nahraj(): string
    {
        $slozka = $this->slozkaZPostu();
        $podslozka = self::bezpecnaPodslozka(FormularNahraniSouboru::textZPostu('podslozka'));
        $this->overZeSlozkaPatriTomutoModulu($slozka, $podslozka);
        $nahravani = $this->nahravani($slozka);
        $soubor = $this->formular->nahranySoubor($nahravani);
        $nazev = FormularNahraniSouboru::textZPostu('nazev') !== ''
            ? FormularNahraniSouboru::textZPostu('nazev')
            : $soubor->puvodniNazevBezPripony();
        $nahravani->ulozNovy($soubor, $podslozka, NahravaniObrazku::bezpecnyNazevDokumentu($nazev));

        return FormularNahraniSouboru::ZPRAVA_NAHRANO;
    }

    private function nahrad(): string
    {
        [$slozka, $cesta] = $this->souborZPostu();
        $nahravani = $this->nahravani($slozka);
        if (!is_file($nahravani->cestaKSouboru($cesta))) {
            throw new NahravaniObrazkuException('Soubor neexistuje.');
        }
        $nahravani->nahrad($this->formular->nahranySoubor($nahravani), $cesta);

        return FormularNahraniSouboru::zpravaONahrazeni($cesta);
    }

    private function smaz(): string
    {
        [$slozka, $cesta] = $this->souborZPostu();
        $nahravani = $this->nahravani($slozka);
        $mime = NahravaniObrazku::mimePodlePripony($nahravani->cestaKSouboru($cesta));
        // výpis nenabízí smazání souboru jiného typu, požadavek ručně poslaný ho taky nesmí smazat
        if ($mime === null || !in_array($mime, $this->slozky[$slozka]['formaty'], true)) {
            throw new NahravaniObrazkuException('Soubory tohoto typu se tu nespravují, jen přes FTP.');
        }
        $nahravani->smaz($cesta);

        return 'Soubor je smazaný.';
    }

    private function slozkaZPostu(): string
    {
        $slozka = FormularNahraniSouboru::textZPostu('slozka');
        if (!isset($this->slozky[$slozka])) {
            throw new NahravaniObrazkuException('Tahle složka tu není.');
        }

        return $slozka;
    }

    /**
     * @return array{string, string} složka a cesta souboru vůči ní
     */
    private function souborZPostu(): array
    {
        $slozka = $this->slozkaZPostu();
        $cesta = FormularNahraniSouboru::textZPostu('cesta');
        $this->overZeSlozkaPatriTomutoModulu($slozka, $cesta);

        return [$slozka, $cesta];
    }

    /**
     * Podsložky v `materialy/<ročník>/eshop` patří Shopu s vlastním právem.
     */
    private function overZeSlozkaPatriTomutoModulu(string $slozka, string $cesta): void
    {
        // lomítko na konci, aby se poznal i samotný adresář
        if (ZpracovaneObrazky::jeAdresarJinehoModulu($slozka . '/' . $cesta . '/')) {
            throw new NahravaniObrazkuException('Tohle se spravuje jinde, obrázky e-shopu ve Finance → Shop.');
        }
    }

    /**
     * @return string cesta podsložek oddělená lomítkem, prázdná pro samotnou složku
     */
    private static function bezpecnaPodslozka(string $zadano): string
    {
        $segmenty = array_values(array_filter(
            explode('/', str_replace('\\', '/', $zadano)),
            static fn (string $segment): bool => trim($segment) !== '',
        ));
        if (count($segmenty) > self::MAX_HLOUBKA_PODSLOZEK) {
            throw new NahravaniObrazkuException(
                sprintf('Podsložky smí být nejvýš %d do hloubky.', self::MAX_HLOUBKA_PODSLOZEK),
            );
        }

        return implode('/', array_map(NahravaniObrazku::bezpecnyNazevDokumentu(...), $segmenty));
    }

    private function nahravani(string $slozka): NahravaniObrazku
    {
        return $this->sluzby[$slozka] ??= new NahravaniObrazku(
            ($this->korenObsahu ?? ZpracovaneObrazky::adresarObsahu()) . '/' . $slozka,
            $this->pouziti,
            maxVelikostMb: SOUBOR_MAX_MB,
            formaty: $this->slozky[$slozka]['formaty'],
            optimalizace: new OptimalizaceObrazku(),
            poCastech: $this->poCastech !== null,
        );
    }

    private function cestaVuciWebu(string $slozka): string
    {
        return 'soubory/obsah/' . $slozka;
    }

    /**
     * @return string|null null, když kořen není `soubory/obsah` webu (testy)
     */
    private function cestaVuciWebuSouboru(string $slozka, string $soubor): ?string
    {
        return $this->korenObsahu === null
            ? $this->cestaVuciWebu($slozka) . '/' . $soubor
            : null;
    }
}
