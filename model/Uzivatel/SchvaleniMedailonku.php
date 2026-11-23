<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Pravo;
use Gamecon\Web\BezpecneHtml;
use Gamecon\XTemplate\XTemplate;

/**
 * Schválení textu medailonku, který vypravěč poslal z Nastavení na webu (@see MedailonekKeSchvaleni): blok s textem
 * ke schválení jednoho vypravěče a jeho zpracování. Je na záložce Uživatel u pracovního uživatele, ve Web →
 * Schvalování medailonků a v detailu medailonku. Organizátor text může před schválením upravit (záměr: texty mají
 * držet jednotnou formu). Schválit smí kdokoli s právem @see Pravo::SCHVALOVANI_MEDAILONKU, mají ho všichni orgové.
 */
final class SchvaleniMedailonku
{
    public const string POST_SCHVALIT = 'schvalitMedailonek';
    public const string POST_ODESLANO = 'schvalitMedailonekOdeslano';
    public const string POST_O_SOBE = 'schvalitMedailonekOSobe';
    public const string POST_DRD = 'schvalitMedailonekDrd';

    private static bool $stylVypsany = false;

    public function __construct(
        private readonly \Uzivatel $schvalujici,
    ) {
    }

    public static function isOdeslanyFormular(): bool
    {
        return post(self::POST_SCHVALIT) !== null;
    }

    public function smiSchvalovat(): bool
    {
        return $this->schvalujici->maPravo(Pravo::SCHVALOVANI_MEDAILONKU);
    }

    /**
     * Obslouží formulář z @see getHtml(). Po akci přesměruje a skončí, jinak nedělá nic.
     *
     * @param int|null    $jenUzivatel záložka Uživatel schvaluje jen pracovního uživatele: formulář mohl zůstat otevřený
     *                                 z doby, kdy se pracovalo s jiným
     * @param string|null $navrat      kam se vrátit, jinak na stránku, odkud formulář přišel
     */
    public function zpracujPost(?int $jenUzivatel = null, ?string $navrat = null): void
    {
        if (! self::isOdeslanyFormular()) {
            return;
        }
        if (! $this->smiSchvalovat()) {
            $this->skonci('Text se neschválil, medailonky schvalovat nesmíš.', \Chyba::CHYBA, $navrat);
        }
        $idUzivatele = (int) post(self::POST_SCHVALIT);
        if ($jenUzivatel !== null && $idUzivatele !== $jenUzivatel) {
            $this->skonci('Text se neschválil, patří jinému uživateli, než se kterým teď pracuješ.', \Chyba::CHYBA, $navrat);
        }
        $keSchvaleni = MedailonekKeSchvaleni::zUzivatele($idUzivatele);
        if ($keSchvaleni === null || ! $keSchvaleni->isCekajici()) {
            $this->skonci('Text už je schválený, nebo ho vypravěč stáhl.', \Chyba::VAROVANI, $navrat);
        }
        // upravit jde jen to, co čeká; ostatní text zůstane, jak je na webu
        $oSobe = $keSchvaleni->oSobe !== null
            ? $this->textZFormulare(self::POST_O_SOBE)
            : null;
        $drd = $keSchvaleni->drd !== null
            ? $this->textZFormulare(self::POST_DRD)
            : null;
        if (mb_strlen($oSobe ?? '') > MedailonekVNastaveni::MAX_DELKA_TEXTU
            || mb_strlen($drd ?? '') > MedailonekVNastaveni::MAX_DELKA_TEXTU
        ) {
            $this->skonci(
                sprintf('Text medailonku může mít nejvýš %d znaků.', MedailonekVNastaveni::MAX_DELKA_TEXTU),
                \Chyba::CHYBA,
                $navrat,
            );
        }
        try {
            MedailonekKeSchvaleni::schval(
                $idUzivatele,
                $oSobe,
                $drd,
                (string) post(self::POST_ODESLANO),
                (int) $this->schvalujici->id(),
            );
        } catch (MedailonekKeSchvaleniException $chyba) {
            $this->skonci($chyba->getMessage(), \Chyba::CHYBA, $navrat);
        }
        $this->skonci('Medailonek je schválený, text je na webu.', \Chyba::OZNAMENI, $navrat);
    }

    /**
     * Blok s textem ke schválení: co je na webu teď, co vypravěč poslal (jde upravit) a jak to bude vypadat.
     *
     * @param string $action kam formulář míří, relativně vůči kořeni adminu (`<base href>`)
     */
    public function getHtml(MedailonekKeSchvaleni $keSchvaleni, string $action): string
    {
        $zverejneny = Medailonek::zId($keSchvaleni->idUzivatele);
        $template = new XTemplate(__DIR__ . '/schvaleni-medailonku.xtpl');
        $template->assign([
            'action'          => htmlspecialchars($action),
            'postSchvalit'    => self::POST_SCHVALIT,
            'postOdeslano'    => self::POST_ODESLANO,
            'idUzivatele'     => $keSchvaleni->idUzivatele,
            'odeslano'        => htmlspecialchars($keSchvaleni->getOdeslanoProFormular()),
            'odeslanoCitelne' => $keSchvaleni->odeslano->format('j. n. Y H:i'),
            'maxDelka'        => MedailonekVNastaveni::MAX_DELKA_TEXTU,
        ]);
        foreach (
            [
                [$keSchvaleni->oSobe, $zverejneny?->oSobe(), 'O sobě', self::POST_O_SOBE],
                [$keSchvaleni->drd, $zverejneny?->drd(), 'Profil Pána jeskyně pro Mistrovství v DrD', self::POST_DRD],
            ] as [$novyText, $zverejnenyHtml, $nazev, $postNazev]
        ) {
            if ($novyText === null) {
                continue;
            }
            $template->assign([
                'nazev'          => $nazev,
                'postText'       => $postNazev,
                'text'           => htmlspecialchars($novyText),
                'nahled'         => BezpecneHtml::zMarkdownu($novyText),
                'zverejnenyHtml' => $zverejnenyHtml,
            ]);
            $template->parse(trim((string) $zverejnenyHtml) !== ''
                ? 'schvaleni.text.zverejneny'
                : 'schvaleni.text.nezverejneny');
            $template->parse(trim($novyText) !== ''
                ? 'schvaleni.text.nahled'
                : 'schvaleni.text.prazdny');
            $template->parse('schvaleni.text');
        }
        $template->parse('schvaleni');
        $html = $template->text('schvaleni');
        if (! self::$stylVypsany) {
            // seznam ve Web → Schvalování medailonků má bloků víc, styl stačí jednou
            $template->parse('styl');
            $html = $template->text('styl') . $html;
            self::$stylVypsany = true;
        }

        return $html;
    }

    private function textZFormulare(string $pole): string
    {
        $text = post($pole);

        return is_string($text)
            ? trim(str_replace("\r\n", "\n", mb_scrub($text, 'UTF-8')))
            : '';
    }

    private function skonci(string $zprava, int $typ, ?string $navrat): never
    {
        // zpráva výjimky i hlášky jsou naše texty, ale do HTML jdou neescapované
        \Chyba::nastav(htmlspecialchars($zprava), $typ);
        back($navrat);
        exit; // back() skončí sám, tohle je pro typy
    }
}
