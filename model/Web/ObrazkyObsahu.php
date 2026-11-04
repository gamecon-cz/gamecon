<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\XTemplate\XTemplate;

/**
 * Obrázky k textu stránky nebo novinky, pod editorem v administraci: nahrání, nahrazení a smazání. Má vlastní
 * formuláře, protože editor textu (@see \DbForm) se odesílá AJAXem. Nové obrázky jdou do adresáře stránky,
 * starší z doby FTP zůstávají, kde jsou, a nahrazují se na místě, aby odkazy dál fungovaly.
 */
final class ObrazkyObsahu
{
    public const PREFIX_V_TEXTU = 'soubory/obsah/';

    private const POST_AKCE = 'obrazkyObsahuAkce';
    private const POST_SOUBOR = 'obrazekObsahu';

    private readonly NahravaniObrazku $nahravani;
    private readonly SouboryTextu $soubory;

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
     * @param string $text Markdown nebo HTML, ve kterém se hledají odkazy na obrázky
     * @param string $adresarProNove kam se ukládají nové obrázky, vůči adresáři obsahu
     * @param string $kCemu do nadpisu „Obrázky nahrané …“
     */
    public function __construct(
        string $text,
        private readonly string $adresarProNove,
        private readonly string $kCemu = 'k této stránce',
        ?NahravaniObrazku $nahravani = null,
    ) {
        $this->nahravani = $nahravani ?? NahravaniObrazku::proObsahWebu();
        $this->soubory = new SouboryTextu($this->nahravani, $text, $adresarProNove);
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
            'nahrat'   => $this->nahrajNovy(),
            'nahradit' => $this->nahrad((string)post('cesta')),
            'smazat'   => $this->smaz((string)post('cesta')),
        }));
    }

    public function html(): string
    {
        $template = new XTemplate(__DIR__ . '/obrazky-obsahu.xtpl');
        $template->assign([
            'postAkce'   => self::POST_AKCE,
            'postSoubor' => self::POST_SOUBOR,
            'accept'     => $this->nahravani->accept(),
            'limit'      => htmlspecialchars($this->nahravani->popisLimitu()),
            'adresar'    => htmlspecialchars(self::PREFIX_V_TEXTU . $this->adresarProNove . '/'),
            'kCemu'      => htmlspecialchars($this->kCemu),
        ]);

        $odkazy = $this->soubory->getOdkazovane();
        foreach ($odkazy as $cesta) {
            $absolutniCesta = $this->soubory->getAbsolutniCesta($cesta);
            $this->assignObrazek($template, $cesta, $absolutniCesta);
            if ($absolutniCesta !== null) {
                $template->assign('tlacitko', 'Nahradit');
                $template->parse('obrazkyObsahu.odkazy.obrazek.existuje');
            } else {
                $template->assign('tlacitko', 'Nahrát');
                $template->parse('obrazkyObsahu.odkazy.obrazek.chybi');
            }
            $template->parse('obrazkyObsahu.odkazy.obrazek');
        }
        if ($odkazy !== []) {
            $template->parse('obrazkyObsahu.odkazy');
        }

        $vlastniObrazky = $this->soubory->getVlastniProVypis();
        $absolutniCesty = array_map($this->soubory->getAbsolutniCesta(...), $vlastniObrazky);
        $kdeJsouPouzity = $this->nahravani->kdeJsouPouzity(array_values(array_filter($absolutniCesty)));
        foreach ($vlastniObrazky as $index => $cesta) {
            $absolutniCesta = $absolutniCesty[$index];
            $this->assignObrazek($template, $cesta, $absolutniCesta);
            $pouziti = $absolutniCesta !== null
                ? $kdeJsouPouzity[$absolutniCesta]
                : [];
            foreach ($pouziti as ['popis' => $popis, 'url' => $url]) {
                $template->assign([
                    'pouzitiPopis' => htmlspecialchars($popis),
                    'pouzitiUrl'   => htmlspecialchars($url),
                ]);
                $template->parse('obrazkyObsahu.vlastni.obrazek.pouziti.misto');
            }
            $template->parse($pouziti !== []
                ? 'obrazkyObsahu.vlastni.obrazek.pouziti'
                : 'obrazkyObsahu.vlastni.obrazek.smazat');
            $template->parse('obrazkyObsahu.vlastni.obrazek');
        }
        if ($vlastniObrazky !== []) {
            $template->parse('obrazkyObsahu.vlastni');
        }

        $template->parse('obrazkyObsahu');

        return $template->text('obrazkyObsahu');
    }

    private static function doTextu(string $cesta): string
    {
        return self::PREFIX_V_TEXTU . ZpracovaneObrazky::zakodujCestu($cesta);
    }

    private function nahrajNovy(): string
    {
        $obrazek = $this->nahravani->overNahranySoubor($_FILES[self::POST_SOUBOR] ?? null);
        $nazev = trim((string)post('nazev')) !== ''
            ? (string)post('nazev')
            : $obrazek->puvodniNazevBezPripony();
        $this->nahravani->ulozNovy(
            $obrazek,
            $this->adresarProNove,
            NahravaniObrazku::bezpecnyNazev($nazev),
        );

        return 'Obrázek nahrán.';
    }

    private function nahrad(string $cesta): string
    {
        if (!$this->soubory->jeNahraditelny($cesta)) {
            throw new NahravaniObrazkuException('Tenhle obrázek tu nahradit nejde, text na něj neodkazuje.');
        }
        $this->nahravani->nahrad(
            $this->nahravani->overNahranySoubor($_FILES[self::POST_SOUBOR] ?? null),
            $cesta,
        );

        return sprintf('Obrázek %s nahrán.', htmlspecialchars(basename($cesta)));
    }

    private function smaz(string $cesta): string
    {
        if (!in_array($cesta, $this->soubory->getVlastni(), true)) {
            throw new NahravaniObrazkuException('Smazat tu jde jen obrázek nahraný ' . $this->kCemu . '.');
        }
        $this->nahravani->smaz($cesta);

        return 'Obrázek je smazaný.';
    }

    private function assignObrazek(XTemplate $template, string $cesta, ?string $absolutniCesta): void
    {
        $template->assign([
            'cesta'         => htmlspecialchars($cesta),
            'vTextu'        => htmlspecialchars(self::PREFIX_V_TEXTU . $cesta),
            'markdown'      => htmlspecialchars(ZapisObrazkuVTextu::markdown(self::doTextu($cesta))),
            'html'          => htmlspecialchars(ZapisObrazkuVTextu::html(self::doTextu($cesta))),
            'acceptObrazku' => $this->nahravani->acceptProNahrazeni((string)NahravaniObrazku::mimePodlePripony($cesta)),
            'nahled'        => $absolutniCesta !== null
                ? htmlspecialchars($this->nahled($absolutniCesta))
                : '',
            'url'           => $absolutniCesta !== null
                ? htmlspecialchars((string)$this->nahravani->urlSouboru($absolutniCesta))
                : '',
        ]);
    }

    private function nahled(string $absolutniCesta): string
    {
        // SVG se v <img> nespouští, ukážeme ho přímo; Imagick by na něj potřeboval další knihovny
        if (NahravaniObrazku::mimePodlePripony($absolutniCesta) === NahravaniObrazku::SVG) {
            return (string)$this->nahravani->urlSouboru($absolutniCesta);
        }
        try {
            return \Nahled::zeSouboru($absolutniCesta)->pokryjOrez(150, 100)->url();
        } catch (\RuntimeException) {
            return '';
        }
    }
}
