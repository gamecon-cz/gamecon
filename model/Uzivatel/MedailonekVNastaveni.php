<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Web\FotkaUzivatele;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\XTemplate\XTemplate;

/**
 * Medailonek v Nastavení na webu: vypravěč, organizátor nebo vedoucí aktivity si v něm sám upraví text o sobě a fotku,
 * které web ukazuje na jeho stránce /<id>. Organizátor totéž dělá za kohokoli v adminu (Aktivity → Medailonky).
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
    private ?FotkaUzivatele $fotka = null;

    public function __construct(
        private readonly \Uzivatel $uzivatel,
        private readonly int $rocnik,
    ) {
    }

    /**
     * Smí si uživatel medailonek upravit? Organizátor, vypravěč (letos role, nebo vede aktivitu) a kdo už medailonek
     * nebo stránku vypravěče má (vedl aktivitu dřív), ať si ji může opravit. Ostatním by sekce jen překážela a uložením
     * by si navíc založili veřejnou stránku /<id>.
     */
    public function isDostupny(): bool
    {
        return $this->dostupny ??= $this->uzivatel->jeOrganizator()
            || \Uzivatel::isVedouciAktivityVRocniku((int) $this->uzivatel->id(), $this->rocnik)
            || $this->maStrankuVypravece();
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
            'oSobe'      => htmlspecialchars($medailonek?->getOSobeMarkdown() ?? ''),
            'drd'        => htmlspecialchars($medailonek?->getDrdMarkdown() ?? ''),
        ]);
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

    private function ulozText(): void
    {
        // ukládá jen pole, která formulář poslal: pole DrD ukazuje jen tomu, pro koho je (@see isDrdRelevantni()), a kdo
        // ho neviděl, nesmí uložením smazat profil, který mu mezitím napsal organizátor v adminu
        $oSobe = $this->textZFormulare(self::POST_O_SOBE);
        $drd = $this->textZFormulare(self::POST_DRD);
        if (mb_strlen($oSobe ?? '') > self::MAX_DELKA_TEXTU || mb_strlen($drd ?? '') > self::MAX_DELKA_TEXTU) {
            // prohlížeč delší text nedovolí napsat (maxlength), sem se dostane jen ručně poskládaný požadavek
            \Chyba::nastav(sprintf('Text medailonku může mít nejvýš %d znaků.', self::MAX_DELKA_TEXTU), \Chyba::CHYBA);
            back($this->getNavrat());
        }
        $this->zalozMedailonek();
        dbQuery(<<<'SQL'
UPDATE medailonky SET o_sobe = COALESCE($1, o_sobe), drd = COALESCE($2, drd) WHERE id_uzivatele = $0
SQL,
            [
                0 => $this->uzivatel->id(),
                1 => $oSobe,
                2 => $drd,
            ],
        );
        \Chyba::nastav('Medailonek je uložený.', \Chyba::OZNAMENI);
        back($this->getNavrat());
    }

    /**
     * @return string|null null, když formulář pole neposlal
     */
    private function textZFormulare(string $pole): ?string
    {
        $text = post($pole);
        if (! is_string($text)) {
            return null;
        }

        // textarea posílá konce řádků jako \r\n, admin je ukládá taky tak, Markdownu je to jedno
        return trim(str_replace("\r\n", "\n", mb_scrub($text, 'UTF-8')));
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
        return ($this->getMedailonek()?->getDrdMarkdown() ?? '') !== ''
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
