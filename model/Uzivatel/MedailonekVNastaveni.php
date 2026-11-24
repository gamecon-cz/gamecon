<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Role\Role;
use Gamecon\Web\BezpecneHtml;
use Gamecon\Web\FotkaUzivatele;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\XTemplate\XTemplate;

/**
 * Medailonek v Nastavení na webu: vypravěč, organizátor nebo vedoucí netechnické aktivity si v něm sám upraví text
 * o sobě a fotku, které web ukazuje na jeho stránce /<id>. Organizátor totéž dělá za kohokoli v adminu (Aktivity → Medailonky).
 * Uživatel je vždy ten přihlášený, formuláře žádné ID neposílají.
 */
final class MedailonekVNastaveni
{
    public const string POST_ULOZIT = 'ulozitMedailonek';
    public const string POST_O_SOBE = 'medailonekOSobe';
    public const string POST_DRD = 'medailonekDrd';
    /**
     * Medailonek je krátké představení; delší text by na stránce vypravěče a v přehledu nikdo nečetl a limit brání
     * i zneužití jako úložiště (sloupce jsou LONGTEXT, samy nic neomezí)
     */
    public const int MAX_DELKA_TEXTU = 10000;
    private const string KOTVA = 'medailonek';

    private ?bool $dostupny = null;
    private ?bool $maStrankuVypravece = null;
    private Medailonek|false|null $medailonek = null;
    private MedailonekKeSchvaleni|false|null $keSchvaleni = null;
    private ?FotkaUzivatele $fotka = null;

    public function __construct(
        private readonly \Uzivatel $uzivatel,
        private readonly int $rocnik,
    ) {
    }

    /**
     * Smí si uživatel medailonek upravit? Organizátor, vypravěč (letos role, nebo letos či dřív vedl netechnickou
     * aktivitu, ať si stránku může opravit) a kdo už medailonek má. Ostatním by sekce jen překážela a uložením by si
     * navíc založili veřejnou stránku /<id>. Ne podle @see Medailonek::maStrankuVypravece(): stránku má i ten, kdo vede
     * jen technickou aktivitu, která ještě neproběhla, vypravěč ale není.
     */
    public function isDostupny(): bool
    {
        return $this->dostupny ??= $this->uzivatel->jeOrganizator()
            || $this->uzivatel->maRoli(Role::LETOSNI_VYPRAVEC($this->rocnik))
            || $this->getMedailonek() !== null
            || $this->vedlNetechnickouAktivitu();
    }

    /**
     * Obslouží formuláře z @see getHtml(). Po akci přesměruje zpět na sekci a skončí, jinak nedělá nic.
     */
    public function zpracujPost(): void
    {
        // PHP příliš velký požadavek zahodí celý, formulář fotky by se pak nepoznal; jiný soubor stránka nenahrává
        NahravaniObrazku::hlidejPrilisVelkyPozadavek($this->getNavrat());
        $ulozitText = post(self::POST_ULOZIT) !== null;
        $fotka = FotkaUzivatele::isOdeslanyFormular();
        if (! $ulozitText && ! $fotka) {
            return;
        }
        if (! $this->isDostupny()) {
            // sekci neviděl, formulář mohl zůstat otevřený z doby, kdy byl vypravěčem
            \Chyba::nastav('Medailonek si může upravit jen vypravěč nebo organizátor.', \Chyba::CHYBA);
            back(URL_WEBU . '/registrace');
        }
        if ($fotka) {
            // stránka /<id> se ukáže jen s medailonkem nebo aktivitou, fotka bez ní by nebyla vidět
            $this->getFotka()->zpracujPost($this->getNavrat(), fn () => $this->zalozMedailonek());
        }
        $this->ulozText();
    }

    public function getHtml(): string
    {
        if (! $this->isDostupny()) {
            return '';
        }
        $medailonek = $this->getMedailonek();
        $keSchvaleni = $this->getKeSchvaleni();
        $cekajici = $keSchvaleni?->isCekajici()
            ? $keSchvaleni
            : null;
        $fotka = $this->getFotka();
        $template = new XTemplate(__DIR__ . '/medailonek-v-nastaveni.xtpl');
        $template->assign([
            'kotva'      => self::KOTVA,
            'urlStranky' => htmlspecialchars(URL_WEBU . '/' . $this->uzivatel->id()),
            'nahled'     => htmlspecialchars($fotka->getNahled()),
            'postAkce'   => FotkaUzivatele::POST_AKCE,
            'akceNahrat' => FotkaUzivatele::AKCE_NAHRAT,
            'akceSmazat' => FotkaUzivatele::AKCE_SMAZAT,
            'postSoubor' => FotkaUzivatele::POST_SOUBOR,
            'accept'     => htmlspecialchars($fotka->getAccept()),
            'limit'      => htmlspecialchars($fotka->getPopisFormatuAVelikosti()),
            'altFotky'   => $fotka->maFotku()
                ? 'Tvoje fotka'
                : 'Výchozí obrázek místo fotky',
            'postUlozit' => self::POST_ULOZIT,
            'postOSobe'  => self::POST_O_SOBE,
            'postDrd'    => self::POST_DRD,
            'maxDelka'   => self::MAX_DELKA_TEXTU,
            // co čeká na schválení, jinak co je na webu
            'oSobe' => htmlspecialchars($cekajici?->oSobe ?? $medailonek?->getOSobeMarkdown() ?? ''),
            'drd'   => htmlspecialchars($cekajici?->drd ?? $medailonek?->getDrdMarkdown() ?? ''),
        ]);
        $this->parsujStav($template, $keSchvaleni, $medailonek);
        $template->parse($this->maStrankuVypravece()
            ? 'medailonek.stranka'
            : 'medailonek.budouciStranka');
        if ($fotka->maFotku()) {
            $template->parse('medailonek.maFotku');
            $template->parse('medailonek.smazatFotku');
        } else {
            $template->parse('medailonek.nemaFotku');
        }
        if ($this->isDrdRelevantni()) {
            $template->parse('medailonek.drd');
        }
        $template->parse('medailonek');

        return $template->text('medailonek');
    }

    /**
     * Stav textu: čeká na schválení, nebo schválený (i text, který napsal organizátor v adminu). Bez textu žádný.
     */
    private function parsujStav(XTemplate $template, ?MedailonekKeSchvaleni $keSchvaleni, ?Medailonek $medailonek): void
    {
        if ($keSchvaleni?->isCekajici()) {
            $template->assign([
                'odeslano' => $keSchvaleni->odeslano->format('j. n. Y'),
                'a'        => $this->uzivatel->koncovkaDlePohlavi(),
            ]);
            $template->parse('medailonek.ceka');

            return;
        }
        if ($keSchvaleni !== null || trim(($medailonek?->getOSobeMarkdown() ?? '') . ($medailonek?->getDrdMarkdown() ?? '')) !== '') {
            $template->assign('schvaleno', $keSchvaleni?->schvaleno !== null
                ? ' ' . $keSchvaleni->schvaleno->format('j. n. Y')
                : '');
            $template->parse('medailonek.schvaleno');
        }
    }

    private function getKeSchvaleni(): ?MedailonekKeSchvaleni
    {
        if ($this->keSchvaleni === null) {
            $this->keSchvaleni = MedailonekKeSchvaleni::zUzivatele((int) $this->uzivatel->id()) ?? false;
        }

        return $this->keSchvaleni ?: null;
    }

    private function ulozText(): void
    {
        // ukládá jen pole, která formulář poslal: pole DrD ukazuje jen tomu, pro koho je (@see isDrdRelevantni()), a kdo
        // ho neviděl, nesmí uložením smazat profil, který mu mezitím napsal organizátor v adminu
        $oSobe = $this->textZFormulare(self::POST_O_SOBE);
        $drd = $this->textZFormulare(self::POST_DRD);
        $cekajici = $this->getKeSchvaleni()?->isCekajici()
            ? $this->getKeSchvaleni()
            : null;
        // kontroly jen pro text, který vypravěč změnil: organizátor v adminu limity nemá a text, který formulář jen
        // poslal zpátky, prohlížeč nehlídá (maxlength platí jen pro psaní)
        $zmenenyOSobe = $this->zmenenyText($oSobe, $cekajici?->oSobe ?? $this->getMedailonek()?->getOSobeMarkdown());
        $zmenenyDrd = $this->zmenenyText($drd, $cekajici?->drd ?? $this->getMedailonek()?->getDrdMarkdown());
        if (mb_strlen($zmenenyOSobe ?? '') > self::MAX_DELKA_TEXTU || mb_strlen($zmenenyDrd ?? '') > self::MAX_DELKA_TEXTU) {
            \Chyba::nastav(sprintf('Text medailonku může mít nejvýš %d znaků.', self::MAX_DELKA_TEXTU), \Chyba::CHYBA);
            back($this->getNavrat());
        }
        if (BezpecneHtml::isPrilisVnoreny($zmenenyOSobe ?? '') || BezpecneHtml::isPrilisVnoreny($zmenenyDrd ?? '')) {
            \Chyba::nastav(
                sprintf('Text má citace nebo seznamy v sobě víc než %d úrovní, zjednoduš ho prosím.', BezpecneHtml::MAX_VNORENI),
                \Chyba::CHYBA,
            );
            back($this->getNavrat());
        }
        // na web text pustí až organizátor, @see MedailonekKeSchvaleni
        if (MedailonekKeSchvaleni::odesli((int) $this->uzivatel->id(), $oSobe, $drd)) {
            \Chyba::nastav(
                'Text medailonku je odeslaný ke schválení. Na webu se ukáže, až ho schválí organizátor.',
                \Chyba::OZNAMENI,
            );
        } else {
            \Chyba::nastav('Text je stejný jako na webu, nic nečeká na schválení.', \Chyba::OZNAMENI);
        }
        back($this->getNavrat());
    }

    /**
     * @return string|null text, když se od původního liší; původní z adminu může mít jiné konce řádků
     */
    private function zmenenyText(?string $text, ?string $puvodni): ?string
    {
        return $text === null || $text === MedailonekKeSchvaleni::normalizujText((string) $puvodni)
            ? null
            : $text;
    }

    /**
     * @return string|null null, když formulář pole neposlal
     */
    private function textZFormulare(string $pole): ?string
    {
        $text = post($pole);

        return is_string($text)
            ? MedailonekKeSchvaleni::normalizujText($text)
            : null;
    }

    private function zalozMedailonek(): void
    {
        dbQuery(<<<'SQL'
INSERT IGNORE INTO medailonky (id_uzivatele, o_sobe, drd) VALUES ($0, '', '')
SQL,
            [
                0 => $this->uzivatel->id(),
            ],
        );
    }

    /**
     * Druhý text medailonku je profil Pána jeskyně pro stránku DrD (widget drd-pj). Ostatním by pole nic neříkalo.
     */
    private function isDrdRelevantni(): bool
    {
        // i čekající prázdný profil: vypravěč ho smazal a pole musí zůstat, jinak by další uložení smazání zahodilo
        return ($this->getKeSchvaleni()?->isCekajici() && $this->getKeSchvaleni()->drd !== null)
            || ($this->getMedailonek()?->getDrdMarkdown() ?? '') !== ''
            || dbOneCol(<<<'SQL'
SELECT 1
FROM akce_organizatori
JOIN akce_seznam ON akce_seznam.id_akce = akce_organizatori.id_akce
WHERE akce_organizatori.id_uzivatele = $0
  AND akce_seznam.rok = $1
  AND akce_seznam.typ = $2
LIMIT 1
SQL,
                [
                    0 => $this->uzivatel->id(),
                    1 => $this->rocnik,
                    2 => TypAktivity::DRD,
                ],
            ) !== null;
    }

    /**
     * Jen netechnickou, stejně jako přehled vypravěčů (@see PrehledVypravecu): kdo vede jen technickou nebo brigádnickou
     * aktivitu, vypravěč není. V jakémkoli stavu, i v přípravě, ať si vypravěč medailonek napíše dřív, než se aktivita
     * zveřejní, a v kterémkoli ročníku, ať si loňský vypravěč opraví svou stránku.
     */
    private function vedlNetechnickouAktivitu(): bool
    {
        return dbOneCol(<<<'SQL'
SELECT 1
FROM akce_organizatori
JOIN akce_seznam ON akce_seznam.id_akce = akce_organizatori.id_akce
WHERE akce_organizatori.id_uzivatele = $0
  AND akce_seznam.rok <= $1
  AND akce_seznam.typ NOT IN ($2)
LIMIT 1
SQL,
            [
                0 => $this->uzivatel->id(),
                1 => $this->rocnik,
                2 => TypAktivity::interniTypy(),
            ],
        ) !== null;
    }

    /**
     * Jednou za požadavek, ptá se databáze a sekce ji potřebuje dvakrát
     */
    private function maStrankuVypravece(): bool
    {
        return $this->maStrankuVypravece ??= Medailonek::maStrankuVypravece($this->uzivatel, $this->rocnik);
    }

    private function getMedailonek(): ?Medailonek
    {
        if ($this->medailonek === null) {
            $this->medailonek = Medailonek::zId((int) $this->uzivatel->id()) ?? false;
        }

        return $this->medailonek ?: null;
    }

    private function getFotka(): FotkaUzivatele
    {
        return $this->fotka ??= new FotkaUzivatele($this->uzivatel, zWebu: true);
    }

    private function getNavrat(): string
    {
        return URL_WEBU . '/registrace#' . self::KOTVA;
    }
}
