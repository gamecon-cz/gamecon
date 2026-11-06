<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\XTemplate\XTemplate;

/**
 * Soubory ke stažení (PDF, dokumenty, zvuk, archivy) k textu stránky nebo novinky, pod editorem v administraci:
 * nahrání, nahrazení a smazání, i velkých souborů po částech. Nové soubory jdou do adresáře stránky, starší z doby
 * FTP zůstávají, kde jsou, a nahrazují se na místě, aby odkazy dál fungovaly.
 *
 * @see ObrazkyObsahu totéž pro obrázky
 */
final class SouboryObsahu
{
    private const POST_AKCE = 'souboryObsahuAkce';
    private const POST_SOUBOR = 'souborObsahu';

    private readonly NahravaniObrazku $nahravani;
    private readonly SouboryTextu $soubory;
    private readonly FormularNahraniSouboru $formular;

    /**
     * @param string $text Markdown nebo HTML, ve kterém se hledají odkazy na soubory
     * @param string $adresarProNove kam se ukládají nové soubory, vůči adresáři obsahu
     * @param string $kCemu do nadpisu „Soubory nahrané …“
     * @param NahravaniPoCastech|null $poCastech s ním se velký soubor pošle po částech; bez něj se posílá celý
     */
    public function __construct(
        string $text,
        private readonly string $adresarProNove,
        private readonly string $kCemu = 'k této stránce',
        ?NahravaniObrazku $nahravani = null,
        ?NahravaniPoCastech $poCastech = null,
    ) {
        $this->nahravani = $nahravani ?? NahravaniObrazku::proDokumentyObsahu($poCastech !== null);
        $this->soubory = new SouboryTextu($this->nahravani, $text, $adresarProNove);
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
            'nahrat'   => $this->nahrajNovy(),
            'nahradit' => $this->nahrad(FormularNahraniSouboru::textZPostu('cesta')),
            'smazat'   => $this->smaz(FormularNahraniSouboru::textZPostu('cesta')),
        })));
    }

    /**
     * Skript, který velký soubor pošle po částech; stránka ho potřebuje jednou.
     */
    public function getSkript(): string
    {
        return $this->formular->getSkript();
    }

    public function html(): string
    {
        $template = new XTemplate(__DIR__ . '/soubory-obsahu.xtpl');
        $template->assign([
            'postAkce'   => self::POST_AKCE,
            'postSoubor' => self::POST_SOUBOR,
            'poCastech'  => $this->formular->getAtributy(),
            'accept'     => $this->nahravani->accept(),
            'limit'      => htmlspecialchars($this->nahravani->popisLimitu()),
            'adresar'    => htmlspecialchars(ObrazkyObsahu::PREFIX_V_TEXTU . $this->adresarProNove . '/'),
            'kCemu'      => htmlspecialchars($this->kCemu),
        ]);

        $odkazy = $this->soubory->getOdkazovane();
        foreach ($odkazy as $cesta) {
            $absolutniCesta = $this->soubory->getAbsolutniCesta($cesta);
            $template->assign([
                'cesta'         => htmlspecialchars($cesta),
                'acceptSouboru' => $this->nahravani->acceptProNahrazeni((string) NahravaniObrazku::mimePodlePripony($cesta)),
                ...$this->getUdajeOSouboru($cesta, $absolutniCesta),
            ]);
            if ($absolutniCesta !== null) {
                $template->assign('tlacitko', 'Nahradit');
                $template->parse('souboryObsahu.odkazy.soubor.existuje');
            } else {
                $template->assign('tlacitko', 'Nahrát');
                $template->parse('souboryObsahu.odkazy.soubor.chybi');
            }
            $template->parse(FormularNahraniSouboru::nazevPrezijeFormular($cesta)
                ? 'souboryObsahu.odkazy.soubor.nahradit'
                : 'souboryObsahu.odkazy.soubor.nespravovany');
            $template->parse('souboryObsahu.odkazy.soubor');
        }
        if ($odkazy !== []) {
            $template->parse('souboryObsahu.odkazy');
        }

        $vlastni = VypisSouboru::odNejnovejsiho($this->nahravani, $this->soubory->getVlastniProVypis());
        $absolutniCesty = array_map($this->soubory->getAbsolutniCesta(...), $vlastni);
        $kdeJsouPouzity = $this->nahravani->kdeJsouPouzity(array_values(array_filter($absolutniCesty)));
        foreach ($vlastni as $index => $cesta) {
            $absolutniCesta = $absolutniCesty[$index];
            $template->assign([
                'cesta'         => htmlspecialchars($cesta),
                'nazev'         => htmlspecialchars(basename($cesta)),
                'acceptSouboru' => $this->nahravani->acceptProNahrazeni((string) NahravaniObrazku::mimePodlePripony($cesta)),
                'markdown'      => htmlspecialchars(ZapisSouboruVTextu::markdown(ObrazkyObsahu::PREFIX_V_TEXTU . $cesta)),
                'html'          => htmlspecialchars(ZapisSouboruVTextu::html(ObrazkyObsahu::PREFIX_V_TEXTU . $cesta)),
                ...$this->getUdajeOSouboru($cesta, $absolutniCesta),
            ]);
            $pouziti = $absolutniCesta !== null
                ? $kdeJsouPouzity[$absolutniCesta]
                : [];
            foreach ($pouziti as ['popis' => $popis, 'url' => $url]) {
                $template->assign([
                    'pouzitiPopis' => htmlspecialchars($popis),
                    'pouzitiUrl'   => htmlspecialchars($url),
                ]);
                $template->parse('souboryObsahu.vlastni.soubor.misto');
            }
            if ($pouziti === []) {
                $template->parse('souboryObsahu.vlastni.soubor.nikde');
            }
            if (!FormularNahraniSouboru::nazevPrezijeFormular($cesta)) {
                $template->parse('souboryObsahu.vlastni.soubor.nespravovany');
            } else {
                $template->parse('souboryObsahu.vlastni.soubor.nahradit');
                if ($pouziti === []) {
                    $template->parse('souboryObsahu.vlastni.soubor.smazat');
                }
            }
            $template->parse('souboryObsahu.vlastni.soubor');
        }
        if ($vlastni !== []) {
            $template->parse('souboryObsahu.vlastni');
        }

        $template->parse('souboryObsahu');

        return $template->text('souboryObsahu');
    }

    /**
     * @return array{url: string, udaje: string, zmeneno: string} prázdné pro soubor, který na serveru chybí
     */
    private function getUdajeOSouboru(string $cesta, ?string $absolutniCesta): array
    {
        if ($absolutniCesta === null) {
            return [
                'url'     => '',
                'udaje'   => '',
                'zmeneno' => '',
            ];
        }

        return [
            'url'     => htmlspecialchars((string) $this->nahravani->urlSouboru($absolutniCesta)),
            'udaje'   => htmlspecialchars(VypisSouboru::getUdaje($cesta, $absolutniCesta)),
            'zmeneno' => VypisSouboru::getNahrano($absolutniCesta),
        ];
    }

    private function nahrajNovy(): string
    {
        $soubor = $this->formular->nahranySoubor($this->nahravani);
        $nazev = FormularNahraniSouboru::textZPostu('nazev') !== ''
            ? FormularNahraniSouboru::textZPostu('nazev')
            : $soubor->puvodniNazevBezPripony();
        $this->nahravani->ulozNovy($soubor, $this->adresarProNove, NahravaniObrazku::bezpecnyNazevDokumentu($nazev));

        return FormularNahraniSouboru::ZPRAVA_NAHRANO;
    }

    private function nahrad(string $cesta): string
    {
        if (!$this->soubory->jeNahraditelny($cesta)) {
            throw new NahravaniObrazkuException('Tenhle soubor tu nahradit nejde, text na něj neodkazuje.');
        }
        $this->nahravani->nahrad($this->formular->nahranySoubor($this->nahravani), $cesta);

        return FormularNahraniSouboru::zpravaONahrazeni($cesta);
    }

    private function smaz(string $cesta): string
    {
        if (!in_array($cesta, $this->soubory->getVlastni(), true)) {
            throw new NahravaniObrazkuException('Smazat tu jde jen soubor nahraný ' . $this->kCemu . '.');
        }
        $this->nahravani->smaz($cesta);

        return 'Soubor je smazaný.';
    }
}
