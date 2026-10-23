<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\XTemplate\XTemplate;

/**
 * Obrázky, které ukazuje galerie e-shopu v přihlášce (web/moduly/prihlaska/prihlaska.php), v administraci pod
 * tabulkou Shopu. Galerie bere všechny obrázky z adresáře ročníku, pořadí i popisek určuje název souboru.
 */
final class ObrazkyEshopu
{
    private const POST_AKCE = 'obrazkyEshopuAkce';
    private const POST_SOUBOR = 'obrazekEshopu';

    private readonly NahravaniObrazku $nahravani;

    public function __construct(?NahravaniObrazku $nahravani = null)
    {
        $this->nahravani = $nahravani ?? NahravaniObrazku::proObrazkyEshopu();
    }

    /**
     * Obslouží formuláře z @see html(). Po akci přesměruje zpět a skončí, jinak nedělá nic.
     */
    public function zpracujPost(): void
    {
        NahravaniObrazku::hlidejPrilisVelkyPozadavek();
        $akce = post(self::POST_AKCE);
        if (!in_array($akce, ['nahrat', 'smazat'], true)) {
            return;
        }
        oznameni(NahravaniObrazku::sOhlasenimChyby(fn (): string => match ($akce) {
            'nahrat' => $this->nahraj(),
            'smazat' => $this->smaz(),
        }));
    }

    public function html(): string
    {
        $template = new XTemplate(__DIR__ . '/obrazky-eshopu.xtpl');
        $template->assign([
            'rocnik' => ROCNIK,
            'accept' => $this->nahravani->accept(),
            'limit'  => htmlspecialchars($this->nahravani->popisLimitu()),
        ]);
        $obrazky = $this->nahravani->obrazkyVAdresari();
        foreach ($obrazky as $soubor) {
            $absolutniCesta = $this->nahravani->cestaKSouboru($soubor);
            $template->assign([
                'soubor'  => htmlspecialchars($soubor),
                'popisek' => htmlspecialchars(ZpracovaneObrazky::popisekObrazkuEshopu($soubor)),
                'nahled'  => htmlspecialchars($this->nahled($absolutniCesta)),
                'url'     => htmlspecialchars((string)$this->nahravani->urlSouboru($absolutniCesta)),
            ]);
            if (!\Obrazek::jeToPodporovanyObrazek($absolutniCesta)) {
                $template->parse('obrazkyEshopu.obrazek.nezobrazi');
            }
            $template->parse('obrazkyEshopu.obrazek');
        }
        if ($obrazky === []) {
            $template->parse('obrazkyEshopu.zadny');
        }
        $template->parse('obrazkyEshopu');

        return $template->text('obrazkyEshopu');
    }

    private function nahraj(): string
    {
        $obrazek = $this->nahravani->overNahranySoubor($_FILES[self::POST_SOUBOR] ?? null);
        $nazev = trim((string)post('nazev')) !== ''
            ? (string)post('nazev')
            : $obrazek->puvodniNazevBezPripony();
        $ulozeno = $this->nahravani->ulozJakoJediny(
            $obrazek,
            '',
            NahravaniObrazku::bezpecnyNazev($nazev, zachovatDiakritikuAMezery: true),
        );

        return sprintf(
            'Obrázek %s je v přihlášce s popiskem „%s“.',
            htmlspecialchars(basename($ulozeno)),
            htmlspecialchars(ZpracovaneObrazky::popisekObrazkuEshopu($ulozeno)),
        );
    }

    private function smaz(): string
    {
        $this->nahravani->smaz(basename((string)post('soubor')));

        return 'Obrázek je smazaný.';
    }

    private function nahled(string $absolutniCesta): string
    {
        try {
            return \Nahled::zeSouboru($absolutniCesta)->pokryjOrez(120, 80)->url();
        } catch (\RuntimeException) {
            return '';
        }
    }
}
