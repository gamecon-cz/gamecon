<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\Role\Role;
use Gamecon\XTemplate\XTemplate;

/**
 * Fotka uživatele, kterou web ukazuje na stránce vypravěče a ve widgetech vypravěčů (@see \Uzivatel::fotka()):
 * náhled, nahrání a smazání. Formulář je v administraci v Medailoncích i na záložce Uživatel; kdo ho smí použít,
 * hlídá stránka, která ho ukazuje.
 */
final class FotkaUzivatele
{
    private const string POST_AKCE = 'akceFotky';
    private const string POST_SOUBOR = 'fotka';

    private readonly NahravaniObrazku $nahravani;
    private ?\Nahled $fotka = null;
    private bool $fotkaNactena = false;

    public function __construct(private readonly \Uzivatel $uzivatel)
    {
        $this->nahravani = NahravaniObrazku::proFotkyUzivatelu();
    }

    /**
     * Hodí se fotka mezi to, co se u uživatele spravuje? Web ji ukazuje u vypravěče (ročníková role, nebo vede
     * aktivitu), u ostatních by se jen pletla do práce. Kdo už nějakou má, tomu ji jde vyměnit nebo smazat.
     */
    public function isRelevantniVRocniku(int $rocnik): bool
    {
        return $this->uzivatel->maRoli(Role::LETOSNI_VYPRAVEC($rocnik))
            || $this->isVedouciAktivityVRocniku($rocnik)
            || $this->getFotka() !== null;
    }

    /**
     * Obslouží formuláře z @see getHtml(). Po akci přesměruje zpět a skončí, jinak nedělá nic.
     */
    public function zpracujPost(): void
    {
        NahravaniObrazku::hlidejPrilisVelkyPozadavek();
        $akce = post(self::POST_AKCE);
        $idUzivatele = (string) $this->uzivatel->id();
        if ($akce === 'nahrat') {
            NahravaniObrazku::sOhlasenimChyby(fn () => $this->nahravani->ulozJakoJediny(
                $this->nahravani->overNahranySoubor($_FILES[self::POST_SOUBOR] ?? null),
                '',
                $idUzivatele,
            ));
            oznameni('Fotka je nahraná.');
        }
        if ($akce === 'smazat') {
            $smazana = NahravaniObrazku::sOhlasenimChyby(fn () => $this->nahravani->smazVsechnyVarianty('', $idUzivatele));
            if (!$smazana) {
                // dvojí odeslání, nebo smazání v jiné záložce, kde je týž formulář (Medailonky i záložka Uživatel)
                varovani('Fotka už byla smazaná, na webu se ukazuje výchozí obrázek.');
            }
            oznameni('Fotka je smazaná, na webu se ukáže výchozí obrázek.');
        }
    }

    /**
     * @param string $skrytaPole HTML skrytých polí, která stránka potřebuje v obou formulářích
     */
    public function getHtml(string $skrytaPole = ''): string
    {
        $fotka = $this->getFotka();
        $template = new XTemplate(__DIR__ . '/fotka-uzivatele.xtpl');
        $template->assign([
            'postAkce'   => self::POST_AKCE,
            'postSoubor' => self::POST_SOUBOR,
            'skrytaPole' => $skrytaPole,
            'jmeno'      => htmlspecialchars((string) $this->uzivatel->jmenoNick()),
            'accept'     => $this->nahravani->accept(),
            'limit'      => htmlspecialchars($this->nahravani->popisLimitu()),
            // stejný výřez jako na stránce vypravěče (web/moduly/aktivity.php)
            'nahled'     => htmlspecialchars((string) ($fotka ?? $this->uzivatel->getVychoziFotka())->kvalita(85)->pokryjOrez(180, 180)),
        ]);
        if ($fotka !== null) {
            $template->parse('fotkaUzivatele.ma');
            $template->parse('fotkaUzivatele.smazat');
        } else {
            $template->parse('fotkaUzivatele.nema');
        }
        $template->parse('fotkaUzivatele');

        return $template->text('fotkaUzivatele');
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

    private function isVedouciAktivityVRocniku(int $rocnik): bool
    {
        return dbOneCol(<<<'SQL'
SELECT 1
FROM akce_organizatori
JOIN akce_seznam ON akce_seznam.id_akce = akce_organizatori.id_akce
WHERE akce_organizatori.id_uzivatele = $0
  AND akce_seznam.rok = $1
LIMIT 1
SQL,
            [0 => $this->uzivatel->id(), 1 => $rocnik],
        ) !== null;
    }
}
