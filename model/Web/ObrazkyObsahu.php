<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\XTemplate\XTemplate;

/**
 * Obrázky k textu stránky nebo novinky, pod editorem v administraci: nahrání nového obrázku,
 * nahrazení obrázku, na který text odkazuje (i dosud chybějícího), a smazání nepoužitého
 * obrázku z adresáře stránky. Editor textu (@see \DbForm) se odesílá AJAXem, proto vlastní formuláře.
 *
 * Nové obrázky jdou do adresáře podle stránky (`soubory/obsah/stranky/<url>/`, `soubory/obsah/blog/<url>/`),
 * starší obrázky z doby FTP zůstávají, kde jsou, a nahrazují se na svém místě, aby odkazy dál fungovaly.
 */
final class ObrazkyObsahu
{
    public const PREFIX_V_TEXTU = 'soubory/obsah/';

    private const POST_AKCE = 'obrazkyObsahuAkce';
    private const POST_SOUBOR = 'obrazekObsahu';
    /**
     * Adresáře, které spravují jiné moduly s vlastním právem: obrázek nahraný sem by se ukázal
     * mezi logy (Loga) nebo v galerii e-shopu v přihlášce (Shop)
     */
    private const CIZI_ADRESARE = '~^(?:sponzori|partneri)/|^materialy/[^/]+/eshop/~i';

    private readonly NahravaniObrazku $nahravani;

    public static function proStranku(\Stranka $stranka): self
    {
        $segmenty = array_filter(
            array_map('slugify', explode('/', (string)$stranka->url())),
            static fn (string $segment): bool => $segment !== '',
        );

        return new self(
            (string)($stranka->raw()['obsah'] ?? ''),
            'stranky/' . ($segmenty !== [] ? implode('/', $segmenty) : 'stranka-' . $stranka->id()),
            'k této stránce',
        );
    }

    public static function proNovinku(\Novinka $novinka): self
    {
        $jeBlog = (int)$novinka->typ() === \Novinka::BLOG;
        $url = slugify((string)$novinka->url());

        return new self(
            (string)($novinka->raw()['text'] ?? ''),
            ($jeBlog ? 'blog/' : 'novinky/') . ($url !== '' ? $url : 'novinka-' . $novinka->id()),
            $jeBlog ? 'k tomuto příspěvku' : 'k této novince',
        );
    }

    /**
     * Obrázky z adresáře obsahu, na které text odkazuje, jako cesty vůči tomuto adresáři.
     * Pozná `soubory/obsah/…` v Markdownu i HTML, relativně i s doménou, i zakódované pro URL.
     *
     * @return list<string>
     */
    public static function odkazyVTextu(string $text): array
    {
        preg_match_all(
            '~soubory/obsah/+([^\s"\'()<>\[\]?#\\\\]+?\.(?:jpe?g|png|gif|webp|svg))(?=[\s"\'()<>\[\]?#]|$)~iu',
            $text,
            $shody,
        );
        $odkazy = [];
        foreach ($shody[1] as $cesta) {
            $segmenty = array_values(array_filter(
                explode('/', rawurldecode($cesta)),
                static fn (string $segment): bool => $segment !== '',
            ));
            // `..` ani skryté soubory nenabízíme, služba by je stejně odmítla
            foreach ($segmenty as $segment) {
                if (str_starts_with($segment, '.')) {
                    continue 2;
                }
            }
            $cesta = implode('/', $segmenty);
            $odkazy[$cesta] = $cesta;
        }

        return array_values($odkazy);
    }

    /**
     * @param string $text Markdown nebo HTML, ve kterém se hledají odkazy na obrázky
     * @param string $adresarProNove kam se ukládají nové obrázky, vůči adresáři obsahu
     * @param string $kCemu do nadpisu „Obrázky nahrané …“
     */
    public function __construct(
        private readonly string $text,
        private readonly string $adresarProNove,
        private readonly string $kCemu = 'k této stránce',
        ?NahravaniObrazku $nahravani = null,
    ) {
        $this->nahravani = $nahravani ?? NahravaniObrazku::proObsahWebu();
    }

    /**
     * Obslouží formuláře z @see html(). Po akci přesměruje zpět a skončí, jinak nedělá nic.
     */
    public function zpracujPost(): void
    {
        if (NahravaniObrazku::bylPozadavekPrilisVelky()) {
            chyba(NahravaniObrazku::zpravaOPrilisVelkemPozadavku());
        }
        $akce = post(self::POST_AKCE);
        if (!in_array($akce, ['nahrat', 'nahradit', 'smazat'], true)) {
            return;
        }
        try {
            $zprava = match ($akce) {
                'nahrat'   => $this->nahrajNovy(),
                'nahradit' => $this->nahrad((string)post('cesta')),
                'smazat'   => $this->smaz((string)post('cesta')),
            };
        } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
            chyba(htmlspecialchars($nahravaniObrazkuException->getMessage()));

            return; // chyba() přesměruje a skončí
        }
        oznameni($zprava);
    }

    public function html(): string
    {
        $t = new XTemplate(__DIR__ . '/obrazky-obsahu.xtpl');
        $t->assign([
            'postAkce'   => self::POST_AKCE,
            'postSoubor' => self::POST_SOUBOR,
            'accept'     => $this->nahravani->accept(),
            'limit'      => htmlspecialchars($this->nahravani->popisLimitu()),
            'adresar'    => htmlspecialchars(self::PREFIX_V_TEXTU . $this->adresarProNove . '/'),
            'kCemu'      => htmlspecialchars($this->kCemu),
        ]);

        $odkazy = $this->odkazyNaSpravovaneObrazky();
        foreach ($odkazy as $cesta) {
            $absolutniCesta = $this->assignObrazek($t, $cesta);
            if ($absolutniCesta !== null) {
                $t->assign('tlacitko', 'Nahradit');
                $t->parse('obrazkyObsahu.odkazy.obrazek.existuje');
            } else {
                $t->assign('tlacitko', 'Nahrát');
                $t->parse('obrazkyObsahu.odkazy.obrazek.chybi');
            }
            $t->parse('obrazkyObsahu.odkazy.obrazek');
        }
        if ($odkazy !== []) {
            $t->parse('obrazkyObsahu.odkazy');
        }

        $vlastniObrazky = $this->vlastniObrazky();
        foreach ($vlastniObrazky as $cesta) {
            $absolutniCesta = $this->assignObrazek($t, $cesta);
            $pouziti = $absolutniCesta !== null
                ? $this->nahravani->kdeJePouzity($absolutniCesta)
                : [];
            foreach ($pouziti as ['popis' => $popis, 'url' => $url]) {
                $t->assign([
                    'pouzitiPopis' => htmlspecialchars($popis),
                    'pouzitiUrl'   => htmlspecialchars($url),
                ]);
                $t->parse('obrazkyObsahu.vlastni.obrazek.pouziti.misto');
            }
            $t->parse($pouziti !== []
                ? 'obrazkyObsahu.vlastni.obrazek.pouziti'
                : 'obrazkyObsahu.vlastni.obrazek.smazat');
            $t->parse('obrazkyObsahu.vlastni.obrazek');
        }
        if ($vlastniObrazky !== []) {
            $t->parse('obrazkyObsahu.vlastni');
        }

        $t->parse('obrazkyObsahu');

        return $t->text('obrazkyObsahu');
    }

    private static function doTextu(string $cesta): string
    {
        return self::PREFIX_V_TEXTU . implode('/', array_map('rawurlencode', explode('/', $cesta)));
    }

    private function nahrajNovy(): string
    {
        $obrazek = $this->nahravani->overNahranySoubor($_FILES[self::POST_SOUBOR] ?? null);
        $nazev = trim((string)post('nazev')) !== ''
            ? (string)post('nazev')
            : $obrazek->puvodniNazevBezPripony();
        $ulozeno = $this->nahravani->ulozNovy(
            $obrazek,
            $this->adresarProNove,
            NahravaniObrazku::bezpecnyNazev($nazev),
        );

        return sprintf(
            'Obrázek je nahraný, do textu ho vložíš jako <code>%s</code>',
            htmlspecialchars('![](' . self::doTextu($this->adresarProNove . '/' . basename($ulozeno)) . ')'),
        );
    }

    private function nahrad(string $cesta): string
    {
        if (!in_array($cesta, [...$this->odkazyNaSpravovaneObrazky(), ...$this->vlastniObrazky()], true)) {
            throw new NahravaniObrazkuException('Tenhle obrázek tu nahradit nejde, text na něj neodkazuje.');
        }
        $this->nahravani->nahrad(
            $this->nahravani->overNahranySoubor($_FILES[self::POST_SOUBOR] ?? null),
            $cesta,
        );

        return sprintf(
            'Obrázek %s je nahraný. Kdo ho už viděl, může mít chvíli v prohlížeči ještě starou verzi.',
            htmlspecialchars(basename($cesta)),
        );
    }

    private function smaz(string $cesta): string
    {
        if (!in_array($cesta, $this->vlastniObrazky(), true)) {
            throw new NahravaniObrazkuException('Smazat tu jde jen obrázek nahraný ' . $this->kCemu . '.');
        }
        $this->nahravani->smaz($cesta);

        return 'Obrázek je smazaný.';
    }

    /**
     * @return list<string>
     */
    private function odkazyNaSpravovaneObrazky(): array
    {
        return array_values(array_filter(
            self::odkazyVTextu($this->text),
            fn (string $cesta): bool => !preg_match(self::CIZI_ADRESARE, $cesta)
                && in_array(NahravaniObrazku::mimePodlePripony($cesta), $this->nahravani->formaty(), true),
        ));
    }

    /**
     * @return list<string>
     */
    private function vlastniObrazky(): array
    {
        return array_map(
            fn (string $soubor): string => $this->adresarProNove . '/' . $soubor,
            $this->nahravani->obrazkyVAdresari($this->adresarProNove),
        );
    }

    /**
     * @return string|null absolutní cesta, pokud obrázek existuje
     */
    private function assignObrazek(XTemplate $t, string $cesta): ?string
    {
        try {
            $absolutniCesta = $this->nahravani->cestaKSouboru($cesta);
        } catch (NahravaniObrazkuException) {
            $absolutniCesta = null;
        }
        if ($absolutniCesta !== null && !is_file($absolutniCesta)) {
            $absolutniCesta = null;
        }
        $t->assign([
            'cesta'         => htmlspecialchars($cesta),
            'vTextu'        => htmlspecialchars(self::PREFIX_V_TEXTU . $cesta),
            'markdown'      => htmlspecialchars('![](' . self::doTextu($cesta) . ')'),
            'acceptObrazku' => (string)NahravaniObrazku::mimePodlePripony($cesta),
            'nahled'        => $absolutniCesta !== null
                ? htmlspecialchars($this->nahled($absolutniCesta))
                : '',
            'url'           => $absolutniCesta !== null
                ? htmlspecialchars((string)ZpracovaneObrazky::urlNaWebu($absolutniCesta))
                : '',
        ]);

        return $absolutniCesta;
    }

    private function nahled(string $absolutniCesta): string
    {
        // SVG se v <img> nespouští, ukážeme ho přímo; Imagick by na něj potřeboval další knihovny
        if (NahravaniObrazku::mimePodlePripony($absolutniCesta) === NahravaniObrazku::SVG) {
            return (string)ZpracovaneObrazky::urlNaWebu($absolutniCesta);
        }
        try {
            return \Nahled::zeSouboru($absolutniCesta)->pasuj(150, 100)->url();
        } catch (\RuntimeException) {
            return '';
        }
    }
}
