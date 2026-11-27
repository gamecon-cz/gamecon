<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

use Gamecon\XTemplate\XTemplate;

/**
 * Stránky Push notifikace a Testovací notifikace v adminu (admin/scripts/modules/web/push-notifikace*.php): zpracují
 * odeslaný formulář a vykreslí příjemce, jeho zařízení, výsledek posledního odeslání a formulář nebo tlačítka.
 * Příjemce je pracovní uživatel adminu.
 */
final readonly class StrankaAdminu
{
    public const string VARIANTA_RUCNI = 'rucni';

    public const string VARIANTA_TESTOVACI = 'testovaci';

    /**
     * Klíč session s rozepsaným ručním formulářem, aby ho chyba při odeslání nesmazala.
     */
    private const string SESSION_FORMULAR = 'push_notifikace_formular';

    /**
     * @param string $urlStranky celá adresa stránky, na kterou se formulář odesílá a kam se po odeslání vrací
     */
    public function __construct(
        private PushNotifikace $pushNotifikace,
        private \Uzivatel $odesilatel,
        private ?\Uzivatel $prijemce,
        private string $urlStranky,
    ) {
    }

    /**
     * Odeslaný formulář: pošle notifikaci, výsledek uloží do session a přesměruje zpět na stránku (obnovení stránky
     * tak nic neodešle znovu). Bez odeslaného formuláře nedělá nic.
     *
     * @param array<string, mixed> $post   $_POST
     * @param array<string, mixed> $server $_SERVER
     *
     * @return string|null adresa, kam přesměrovat, null když formulář nikdo neodeslal
     */
    public function zpracuj(array $post, array $server, string $urlAdmin): ?string
    {
        $akce = $post['pushNotifikaceAkce'] ?? null;
        if (! is_string($akce)) {
            return null;
        }
        try {
            if (! OdeslaniZAdminu::isPozadavekZAdminu($server, $urlAdmin)) {
                throw new ChybaPushNotifikace('Formulář nepřišel ze stránky adminu, nic se neodeslalo.', 403);
            }
            // Pracovní uživatel je jeden na session: kdo ho mezitím přepnul v jiné záložce, poslal by notifikaci jinému.
            if ($this->prijemce === null || (int) ($post['idPrijemce'] ?? 0) !== (int) $this->prijemce->id()) {
                throw new ChybaPushNotifikace('Vybraný uživatel se mezitím změnil nebo žádný vybraný není, nic se neodeslalo. Zkontroluj příjemce a pošli znovu.');
            }
            $zprava = match ($akce) {
                self::VARIANTA_RUCNI => ZpravaNotifikace::vytvor(
                    TypNotifikace::OBECNA,
                    self::getText($post, 'nadpis'),
                    self::getText($post, 'text'),
                    self::getText($post, 'odkaz'),
                ),
                self::VARIANTA_TESTOVACI => (TestovaciNotifikace::tryFrom(self::getText($post, 'testovaci'))
                    ?? throw new ChybaPushNotifikace('Neznámá testovací notifikace.'))->getZprava(),
                default => throw new ChybaPushNotifikace('Neznámá akce.'),
            };
            $vysledky = $this->pushNotifikace->getOdeslaniZAdminu()->posli($this->odesilatel, $this->prijemce, $zprava);
            OdeslaniZAdminu::ulozVysledek($zprava->nadpis, (int) $this->prijemce->id(), $vysledky);
            unset($_SESSION[self::SESSION_FORMULAR]);
        } catch (ChybaPushNotifikace $chyba) {
            if ($akce === self::VARIANTA_RUCNI) {
                $_SESSION[self::SESSION_FORMULAR] = [
                    'nadpis' => self::getText($post, 'nadpis'),
                    'text'   => self::getText($post, 'text'),
                    'odkaz'  => self::getText($post, 'odkaz'),
                ];
            }
            \Chyba::nastav($chyba->getMessage(), \Chyba::CHYBA);
        }

        return $this->urlStranky;
    }

    public function getHtml(string $varianta): string
    {
        $t = new XTemplate(__DIR__ . '/templates/admin.xtpl');
        $lzeOdeslat = $this->pushNotifikace->isZapnuto() && $this->prijemce !== null;
        $spolecne = [
            'urlStranky' => self::e($this->urlStranky),
            'idPrijemce' => (int) ($this->prijemce?->id() ?? 0),
            'zakazano'   => $lzeOdeslat
                ? ''
                : 'disabled',
            'limitOdesilatele' => ZaznamOdeslani::LIMIT_ODESILATELE,
            'limitPrijemce'    => ZaznamOdeslani::LIMIT_PRIJEMCE,
            'oknoMinut'        => intdiv(ZaznamOdeslani::OKNO_SEKUND, 60),
        ];
        $t->assign($spolecne);

        if (! $this->pushNotifikace->isNakonfigurovano()) {
            $t->parse('pushNotifikace.bezKlicu');
        } elseif (! $this->pushNotifikace->isZapnutoVNastaveni()) {
            $t->parse('pushNotifikace.vypnuto');
        }
        $this->vykresliPrijemce($t);
        $this->vykresliVysledek($t);
        if ($varianta === self::VARIANTA_TESTOVACI) {
            $this->vykresliTestovaci($t, $spolecne);
        } else {
            $this->vykresliRucni($t);
        }
        $t->parse('pushNotifikace');

        return $t->text('pushNotifikace');
    }

    private function vykresliPrijemce(XTemplate $t): void
    {
        if ($this->prijemce === null) {
            $t->parse('pushNotifikace.bezPrijemce');

            return;
        }
        $zarizeni = $this->pushNotifikace->isNakonfigurovano()
            ? $this->pushNotifikace->getSubscriptions()->getAktivniUzivatele((int) $this->prijemce->id())
            : [];
        $t->assign([
            'jmeno'         => self::e((string) $this->prijemce->jmenoNick()),
            'id'            => (int) $this->prijemce->id(),
            'pocetZarizeni' => count($zarizeni),
        ]);
        foreach ($zarizeni as $subscription) {
            $t->assign([
                'zarizeni'         => self::e($subscription->zarizeni),
                'pushSluzba'       => self::e($subscription->getPushSluzba()),
                'vytvoreno'        => $subscription->vytvoreno->format('j. n. Y H:i'),
                'naposledyPredano' => $subscription->naposledyPredano?->format('j. n. Y H:i') ?? 'zatím nic',
                'pocetChyb'        => $subscription->pocetChybVRade,
            ]);
            $t->parse('pushNotifikace.prijemce.zarizeni.radek');
        }
        $t->parse($zarizeni === []
            ? 'pushNotifikace.prijemce.bezZarizeni'
            : 'pushNotifikace.prijemce.zarizeni');
        $t->parse('pushNotifikace.prijemce');
    }

    private function vykresliVysledek(XTemplate $t): void
    {
        $vysledek = OdeslaniZAdminu::vyzvedniVysledek();
        // výsledek jiného příjemce (mezitím přepnutý pracovní uživatel) by u tohohle mátl
        if ($vysledek === null || $vysledek['idPrijemce'] !== (int) ($this->prijemce?->id() ?? 0)) {
            return;
        }
        $t->assign('nadpis', self::e($vysledek['nadpis']));
        foreach ($vysledek['vysledky'] as $vysledekZarizeni) {
            $t->assign([
                'zarizeni'   => self::e($vysledekZarizeni->zarizeni),
                'pushSluzba' => self::e($vysledekZarizeni->pushSluzba),
                'stav'       => self::e($vysledekZarizeni->stav->getPopis()),
                'httpKod'    => $vysledekZarizeni->httpKod ?? '–',
                'duvod'      => self::e($vysledekZarizeni->duvod),
            ]);
            $t->parse('pushNotifikace.vysledek.tabulka.radek');
        }
        $t->parse($vysledek['vysledky'] === []
            ? 'pushNotifikace.vysledek.bezZarizeni'
            : 'pushNotifikace.vysledek.tabulka');
        $t->parse('pushNotifikace.vysledek');
    }

    private function vykresliRucni(XTemplate $t): void
    {
        $rozepsany = $_SESSION[self::SESSION_FORMULAR] ?? [];
        unset($_SESSION[self::SESSION_FORMULAR]);
        $t->assign([
            'nadpis'    => self::e(self::getText($rozepsany, 'nadpis')),
            'text'      => self::e(self::getText($rozepsany, 'text')),
            'odkaz'     => self::e(self::getText($rozepsany, 'odkaz')),
            'maxNadpis' => ZpravaNotifikace::MAX_DELKA_NADPISU,
            'maxText'   => ZpravaNotifikace::MAX_DELKA_TEXTU,
            'maxOdkaz'  => OdkazNotifikace::MAX_DELKA,
        ]);
        $t->parse('pushNotifikace.rucni');
    }

    /**
     * @param array<string, mixed> $spolecne hodnoty, které řádky tabulky přepisují stejnojmennými (nadpis, text)
     */
    private function vykresliTestovaci(XTemplate $t, array $spolecne): void
    {
        $t->assign('oznaceni', self::e(TestovaciNotifikace::OZNACENI));
        foreach (TestovaciNotifikace::cases() as $testovaci) {
            $zprava = $testovaci->getZprava();
            $t->assign($spolecne + [
                'hodnota'   => self::e($testovaci->value),
                'popisek'   => self::e($testovaci->getPopisek()),
                'nadpis'    => self::e($zprava->nadpis),
                'text'      => self::e($zprava->text),
                'coOveruje' => self::e($testovaci->getCoOveruje()),
                'urgence'   => self::e($zprava->urgence->getNazev()),
                'ttl'       => self::e(self::getDobaSlovy($zprava->ttl)),
                'tag'       => $zprava->tag === null
                    ? ''
                    : ', nahrazuje notifikaci „' . self::e($zprava->tag) . '“',
            ]);
            $t->parse('pushNotifikace.testovaci.notifikace');
        }
        $t->parse('pushNotifikace.testovaci');
    }

    private static function getDobaSlovy(int $sekund): string
    {
        return match (true) {
            $sekund >= 86400 => 'nejdéle ' . intdiv($sekund, 86400) . ' d',
            $sekund >= 3600  => 'nejdéle ' . intdiv($sekund, 3600) . ' h',
            default          => 'nejdéle ' . intdiv($sekund, 60) . ' min',
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function getText(array $data, string $klic): string
    {
        $hodnota = $data[$klic] ?? '';

        return is_string($hodnota)
            ? $hodnota
            : '';
    }

    private static function e(string $hodnota): string
    {
        return htmlspecialchars($hodnota, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
