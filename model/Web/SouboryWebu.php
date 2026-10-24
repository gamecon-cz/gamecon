<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\XTemplate\XTemplate;

/**
 * Stránka Web → Soubory: PDF, zvuk, archivy a tabulky ke stažení ve složkách `soubory/obsah`, na které odkazují
 * texty webu. Pevný seznam složek a typů je v @see self::SLOZKY, nic jiného se odsud nezapíše ani nesmaže.
 */
final class SouboryWebu
{
    /**
     * Složky v `soubory/obsah`, název složky => k čemu slouží a jaké soubory do ní patří. Obrázky e-shopu
     * (`materialy/<ročník>/eshop`) mají vlastní modul a tady se nevypisují.
     */
    public const SLOZKY = [
        'drd' => [
            'nazev'   => 'Mistrovství v Dračím doupěti',
            'popis'   => 'Archiv stránky DrD: dobrodružství, deníky postav a nahrávky.',
            'formaty' => [
                NahravaniObrazku::PDF,
                NahravaniObrazku::XLSX,
                NahravaniObrazku::MP3,
                NahravaniObrazku::ZIP,
                ...NahravaniObrazku::RASTROVE_FORMATY,
            ],
        ],
        'download' => [
            'nazev'   => 'Ke stažení',
            'popis'   => 'Soubory ke stažení, například tisková kronika nebo orientační brožura.',
            'formaty' => [NahravaniObrazku::PDF],
        ],
        'materialy' => [
            'nazev'   => 'Materiály a formuláře',
            'popis'   => 'Podklady odkazované z textů stránek a z přihlášky, třeba souhlas rodičů, po ročnících v podsložkách.',
            'formaty' => [NahravaniObrazku::PDF, ...NahravaniObrazku::RASTROVE_FORMATY],
        ],
        'celohra' => [
            'nazev'   => 'Celohra',
            'popis'   => 'Podklady k celohře.',
            'formaty' => [NahravaniObrazku::PDF],
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

    /**
     * @param array<string, array{nazev: string, popis: string, formaty: list<string>}> $slozky
     * @param string|null $korenObsahu null je `soubory/obsah` webu
     */
    public function __construct(
        private readonly array $slozky = self::SLOZKY,
        private readonly ?string $korenObsahu = null,
        private readonly PouzitiObrazku $pouziti = new PouzitiObrazku(),
    ) {
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
        oznameni(NahravaniObrazku::sOhlasenimChyby(fn (): string => match ($akce) {
            'nahrat'   => $this->nahraj(),
            'nahradit' => $this->nahrad(),
            'smazat'   => $this->smaz(),
        }));
    }

    public function html(): string
    {
        $template = new XTemplate(__DIR__ . '/soubory-webu.xtpl');
        $template->assign([
            'postAkce'   => self::POST_AKCE,
            'postSoubor' => self::POST_SOUBOR,
        ]);
        $odkazyVTextech = $this->pouziti->odkazyVeSlozkach(array_map(
            fn (int|string $slozka): string => $this->cestaVuciWebu((string) $slozka),
            array_keys($this->slozky),
        ));

        foreach ($this->slozky as $nazevSlozky => $nastaveni) {
            // číselný název složky je v poli celé číslo
            $slozka = (string) $nazevSlozky;
            $nahravani = $this->nahravani($slozka);
            $soubory = array_values(array_filter(
                $nahravani->souboryVeStromu('', self::MAX_HLOUBKA_VYPISU),
                static fn (string $soubor): bool => !ZpracovaneObrazky::jeAdresarJinehoModulu($slozka . '/' . $soubor),
            ));
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
     * Cesta se ve formuláři posílá jako text, název souboru, který se v textu nezachová (kódování z FTP,
     * mezera na okraji), by se po cestě zpátky nenašel.
     */
    private static function nazevPrezijeFormular(string $cesta): bool
    {
        return mb_check_encoding($cesta, 'UTF-8') && $cesta === trim($cesta);
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
            'typ'           => htmlspecialchars(NahravaniObrazku::nazevFormatuPodlePripony($soubor) ?? mb_strtoupper(pathinfo($soubor, PATHINFO_EXTENSION))),
            'velikost'      => NahravaniObrazku::lidskaVelikost((int) @filesize($absolutniCesta)),
            'zmeneno'       => date('j. n. Y', (int) @filemtime($absolutniCesta)),
            'markdown'      => $cestaVuciWebu === null
                ? ''
                : htmlspecialchars(self::markdownOdkaz($cestaVuciWebu)),
            'acceptSouboru' => (string) $mime,
        ]);

        if ($mime === null || !in_array($mime, $povoleneFormaty, true) || !self::nazevPrezijeFormular($soubor)) {
            $template->parse('souboryWebu.slozka.soubor.nespravovany');
        } else {
            $template->parse('souboryWebu.slozka.soubor.nahradit');
            if ($pouziti === []) {
                $template->parse('souboryWebu.slozka.soubor.smazat');
            }
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
        $podslozka = self::bezpecnaPodslozka($this->textZPostu('podslozka'));
        $this->overZeSlozkaPatriTomutoModulu($slozka, $podslozka);
        $nahravani = $this->nahravani($slozka);
        $soubor = $nahravani->overNahranySoubor($_FILES[self::POST_SOUBOR] ?? null);
        $nazev = $this->textZPostu('nazev') !== ''
            ? $this->textZPostu('nazev')
            : $soubor->puvodniNazevBezPripony();
        $ulozeno = $nahravani->ulozNovy($soubor, $podslozka, NahravaniObrazku::bezpecnyNazevDokumentu($nazev));

        $cestaVuciWebu = $this->cestaVuciWebuSouboru($slozka, ($podslozka === '' ? '' : $podslozka . '/') . basename($ulozeno));

        return sprintf(
            'Soubor je nahraný jako %s, do textu ho vložíš jako <code>%s</code>.',
            htmlspecialchars(basename($ulozeno)),
            $cestaVuciWebu === null
                ? '…'
                : htmlspecialchars(self::markdownOdkaz($cestaVuciWebu)),
        );
    }

    private function nahrad(): string
    {
        [$slozka, $cesta] = $this->souborZPostu();
        $nahravani = $this->nahravani($slozka);
        if (!is_file($nahravani->cestaKSouboru($cesta))) {
            throw new NahravaniObrazkuException('Soubor neexistuje.');
        }
        $nahravani->nahrad($nahravani->overNahranySoubor($_FILES[self::POST_SOUBOR] ?? null), $cesta);

        return sprintf(
            'Soubor %s je nahraný. Kdo ho už viděl, může mít chvíli v prohlížeči ještě starou verzi.',
            htmlspecialchars(basename($cesta)),
        );
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

    private function textZPostu(string $nazev): string
    {
        $hodnota = post($nazev);

        return is_string($hodnota)
            ? trim($hodnota)
            : '';
    }

    private function slozkaZPostu(): string
    {
        $slozka = $this->textZPostu('slozka');
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
        $cesta = $this->textZPostu('cesta');
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

    private static function markdownOdkaz(string $cestaVuciWebu): string
    {
        return sprintf(
            '[%s](%s)',
            pathinfo($cestaVuciWebu, PATHINFO_FILENAME),
            ZpracovaneObrazky::zakodujCestu($cestaVuciWebu),
        );
    }
}
