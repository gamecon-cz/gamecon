<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\Role\Role;
use Gamecon\XTemplate\XTemplate;

/**
 * Fotka uživatele, kterou web ukazuje na stránce vypravěče a ve widgetech vypravěčů (@see \Uzivatel::fotka()):
 * náhled, nahrání a smazání. Formulář je v administraci v Medailoncích i na záložce Uživatel, svou fotku si vypravěč
 * mění i sám v Nastavení na webu (@see \Gamecon\Uzivatel\MedailonekVNastaveni, vlastní šablona nad stejnými poli);
 * kdo ho smí použít, hlídá stránka, která ho ukazuje.
 */
final class FotkaUzivatele
{
    public const string POST_AKCE = 'akceFotky';
    public const string POST_SOUBOR = 'fotka';
    public const string AKCE_NAHRAT = 'nahrat';
    public const string AKCE_SMAZAT = 'smazat';

    private ?NahravaniObrazku $nahravani = null;
    private ?\Nahled $fotka = null;
    private bool $fotkaNactena = false;

    /**
     * @param bool $zWebu nahrává si ji uživatel sám v Nastavení na webu, platí pro ni menší limity
     */
    public function __construct(
        private readonly \Uzivatel $uzivatel,
        private readonly bool $zWebu = false,
    ) {
    }

    /**
     * Poslal organizátor formulář z @see getHtml()? Pro stránku, která ho zrovna zpracovat nemůže (organizátor
     * mezitím přišel o práva), a má proto ohlásit, proč se nic nestalo.
     */
    public static function isOdeslanyFormular(): bool
    {
        return in_array(post(self::POST_AKCE), [self::AKCE_NAHRAT, self::AKCE_SMAZAT], true);
    }

    /**
     * Hodí se fotka mezi to, co se u uživatele spravuje? Web ji ukazuje u vypravěče (ročníková role, nebo vede
     * aktivitu), u ostatních by se jen pletla do práce. Kdo už nějakou má, tomu ji jde vyměnit nebo smazat.
     */
    public function isRelevantniVRocniku(int $rocnik): bool
    {
        return $this->uzivatel->maRoli(Role::LETOSNI_VYPRAVEC($rocnik))
            || \Uzivatel::isVedouciAktivityVRocniku($this->uzivatel->id(), $rocnik)
            || $this->getFotka() !== null;
    }

    /**
     * Obslouží formuláře z @see getHtml(). Po akci přesměruje zpět a skončí, jinak nedělá nic.
     *
     * @param string|null $navrat kam se po akci vrátit, jinak na stránku, odkud formulář přišel
     * @param (callable(): void)|null $poNahrani co ještě udělat, když se fotka nahrála
     */
    public function zpracujPost(?string $navrat = null, ?callable $poNahrani = null): void
    {
        NahravaniObrazku::hlidejPrilisVelkyPozadavek($navrat);
        $akce = post(self::POST_AKCE);
        $idUzivatele = (string) $this->uzivatel->id();
        if ($akce === self::AKCE_NAHRAT) {
            NahravaniObrazku::sOhlasenimChyby(
                fn () => $this->getNahravani()->ulozJakoJediny(
                    $this->getNahravani()->overNahranySoubor($_FILES[self::POST_SOUBOR] ?? null),
                    '',
                    $idUzivatele,
                ),
                $navrat,
            );
            if ($poNahrani !== null) {
                $poNahrani();
            }
            \Chyba::nastav('Fotka je nahraná.', \Chyba::OZNAMENI);
            back($navrat);
        }
        if ($akce === self::AKCE_SMAZAT) {
            $smazana = NahravaniObrazku::sOhlasenimChyby(
                fn () => $this->getNahravani()->smazVsechnyVarianty('', $idUzivatele),
                $navrat,
            );
            if ($smazana) {
                \Chyba::nastav('Fotka je smazaná, na webu se ukáže výchozí obrázek.', \Chyba::OZNAMENI);
            } else {
                // dvojí odeslání, nebo smazání v jiné záložce, kde je týž formulář (Medailonky i záložka Uživatel)
                \Chyba::nastav('Fotka už byla smazaná, na webu se ukazuje výchozí obrázek.', \Chyba::VAROVANI);
            }
            back($navrat);
        }
    }

    public function getHtml(): string
    {
        $template = new XTemplate(__DIR__ . '/fotka-uzivatele.xtpl');
        $template->assign([
            'postAkce'   => self::POST_AKCE,
            'postSoubor' => self::POST_SOUBOR,
            'jmeno'      => htmlspecialchars((string) $this->uzivatel->jmenoNick()),
            'accept'     => $this->getAccept(),
            'limit'      => htmlspecialchars($this->getPopisLimitu()),
            'nahled'     => htmlspecialchars($this->getNahled()),
        ]);
        if ($this->maFotku()) {
            $template->parse('fotkaUzivatele.ma');
            $template->parse('fotkaUzivatele.smazat');
        } else {
            $template->parse('fotkaUzivatele.nema');
        }
        $template->parse('fotkaUzivatele');

        return $template->text('fotkaUzivatele');
    }

    public function maFotku(): bool
    {
        return $this->getFotka() !== null;
    }

    /**
     * Adresa náhledu fotky, nebo výchozího obrázku, když fotka chybí; stejný výřez jako na stránce vypravěče
     * (web/moduly/aktivity.php)
     */
    public function getNahled(): string
    {
        return (string) ($this->getFotka() ?? $this->uzivatel->getVychoziFotka())->kvalita(85)->pokryjOrez(180, 180);
    }

    /**
     * MIME typy pro `accept` výběru souboru
     */
    public function getAccept(): string
    {
        return $this->getNahravani()->accept();
    }

    /**
     * Jaké formáty a jak velký soubor server přijme a jak obrázek upraví
     */
    public function getPopisLimitu(): string
    {
        return $this->getNahravani()->popisLimitu();
    }

    /**
     * Jaké formáty a jak velký soubor server přijme
     */
    public function getPopisFormatuAVelikosti(): string
    {
        return $this->getNahravani()->popisFormatuAVelikosti();
    }

    /**
     * Až když je potřeba: zjišťuje, co umí ImageMagick, a záložka Uživatel ji u většiny uživatelů nepotřebuje.
     */
    private function getNahravani(): NahravaniObrazku
    {
        return $this->nahravani ??= $this->zWebu
            ? NahravaniObrazku::proFotkuZWebu()
            : NahravaniObrazku::proFotkyUzivatelu();
    }

    /**
     * Soubor fotky se hledá globem, proto jen jednou za požadavek.
     */
    private function getFotka(): ?\Nahled
    {
        if (!$this->fotkaNactena) {
            $this->fotka = $this->uzivatel->fotka();
            $this->fotkaNactena = true;
        }

        return $this->fotka;
    }
}
