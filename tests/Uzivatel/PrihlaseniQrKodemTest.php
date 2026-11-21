<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Uzivatel\PrihlaseniQrKodem;

/**
 * Přihlášení QR kódem: zařízení bez hesla ukáže kód a číslo, přihlášený uživatel ho na telefonu schválí vybráním
 * stejného čísla a zařízení se přihlásí za něj. Zařízení jsou tu session a cookie, se kterými prohlížeč přijde.
 */
class PrihlaseniQrKodemTest extends AbstractPrihlaseniNaZarizenichTestDb
{
    private const string IP = '192.0.2.10';
    private const string USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

    public function testSchvaleniSpravnymCislemPrihlasiZarizeni(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        $zadost = $this->zalozZadost($pocitac);

        self::assertSame(PrihlaseniQrKodem::STAV_CEKA, $this->stav($pocitac));
        self::assertSame(PrihlaseniQrKodem::SCHVALENO, $this->schval($telefon, $zadost['kod'], $zadost['cislo']));
        self::assertSame(PrihlaseniQrKodem::STAV_PRIHLASENO, $this->stav($pocitac));

        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($pocitac));
        self::assertNull((new PrihlaseniQrKodem($pocitac['session'], false))->getZadost(), 'Uplatněnou žádost má session zapomenout.');
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($telefon), 'Telefon, který schválil, zůstane přihlášený.');
    }

    /**
     * Kdo má jen odkaz bez obrazovky zařízení, číslo nezná, a když netrefí, druhý pokus už nemá: ze tří čísel by
     * uhodl skoro jistě.
     */
    public function testSpatneCisloZadostZamitne(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        $zadost = $this->zalozZadost($pocitac);
        $spatneCislo = $this->spatneCislo($zadost['kod'], $zadost['cislo']);

        self::assertSame(PrihlaseniQrKodem::SPATNE_CISLO, $this->schval($telefon, $zadost['kod'], $spatneCislo));
        self::assertSame(PrihlaseniQrKodem::NEPLATNY_KOD, $this->schval($telefon, $zadost['kod'], $zadost['cislo']));
        self::assertSame(PrihlaseniQrKodem::STAV_ZAMITNUTO, $this->stav($pocitac));
        self::assertNull($this->idPrihlasenehoNa($pocitac));
    }

    /**
     * Kdo kód vyfotí přes rameno, přihlášení může nanejvýš schválit, přihlásit se jím nemůže: přihlásí se jen
     * session, která žádost založila.
     */
    public function testKodNeprihlasiJinyProhlizec(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        $zadost = $this->zalozZadost($pocitac);
        $zlodej = $this->zarizeni();
        $this->schval($telefon, $zadost['kod'], $zadost['cislo']);

        self::assertSame(PrihlaseniQrKodem::STAV_ZADNA, $this->stav($zlodej));
        self::assertNull($this->idPrihlasenehoNa($zlodej));
        self::assertSame(PrihlaseniQrKodem::STAV_PRIHLASENO, $this->stav($pocitac));
    }

    /**
     * Požadavky jedné session PHP řadí za sebe, kopie session (souběžné karty bez zámku) ale schválení uplatní jen
     * jednou.
     */
    public function testSchvaleniSeUplatniJenJednou(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        $zadost = $this->zalozZadost($pocitac);
        $kopie = $pocitac;
        $this->schval($telefon, $zadost['kod'], $zadost['cislo']);

        self::assertSame(PrihlaseniQrKodem::STAV_PRIHLASENO, $this->stav($pocitac));
        // Zápis stejného času ve stejné sekundě by MariaDB nepočítala jako změnu řádku, ani bez podmínky na uplatnění.
        dbQuery(<<<'SQL'
            UPDATE prihlaseni_qr SET uplatneno_unix = uplatneno_unix - 1 WHERE id = $0
            SQL, [
            0 => $zadost['id'],
        ]);
        self::assertSame(PrihlaseniQrKodem::STAV_VYPRSELO, $this->stav($kopie));
        self::assertNull($this->idPrihlasenehoNa($kopie));
    }

    /**
     * Schválení platí s razítkem přihlášení telefonu, který schválil. Odhlášení ostatních zařízení nebo nové heslo
     * mezi schválením a přihlášením zařízení ho zruší.
     */
    public function testZmenaRazitkaPoSchvaleniZarizeniNeprihlasi(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        $zadost = $this->zalozZadost($pocitac);
        $this->schval($telefon, $zadost['kod'], $zadost['cislo']);

        $this->pozadavek($telefon, static fn () => \Uzivatel::zSession()->odhlasNaOstatnichZarizenich());

        self::assertSame(PrihlaseniQrKodem::STAV_VYPRSELO, $this->stav($pocitac));
        self::assertNull($this->idPrihlasenehoNa($pocitac));
    }

    public function testVyprselyKodNejdeSchvalit(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        $zadost = $this->zalozZadost($pocitac);
        $this->posunVznikDoMinulosti($zadost['id'], PrihlaseniQrKodem::PLATNOST_SEKUND + 1);

        self::assertSame(PrihlaseniQrKodem::NEPLATNY_KOD, $this->schval($telefon, $zadost['kod'], $zadost['cislo']));
        self::assertSame(PrihlaseniQrKodem::STAV_VYPRSELO, $this->stav($pocitac));
        self::assertSame(PrihlaseniQrKodem::STAV_VYPRSELO, PrihlaseniQrKodem::najdiProSchvaleni($zadost['kod'])['stav']);
    }

    /**
     * Zařízení, které se po schválení dlouho neozvalo, se už nepřihlásí: obrazovku mezitím mohl převzít někdo jiný.
     */
    public function testSchvaleniPrestaneDlouhoPoSchvaleni(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        $zadost = $this->zalozZadost($pocitac);
        $this->schval($telefon, $zadost['kod'], $zadost['cislo']);
        dbQuery(<<<'SQL'
            UPDATE prihlaseni_qr SET schvaleno_unix = schvaleno_unix - 121 WHERE id = $0
            SQL, [
            0 => $zadost['id'],
        ]);

        self::assertSame(PrihlaseniQrKodem::STAV_VYPRSELO, $this->stav($pocitac));
        self::assertNull($this->idPrihlasenehoNa($pocitac));
    }

    public function testZaskrtnuteTrvalePrihlaseniPrezijeZavreniProhlizece(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);

        foreach ([true, false] as $trvale) {
            $pocitac = $this->zarizeni();
            $zadost = $this->zalozZadost($pocitac, $trvale);
            $this->schval($telefon, $zadost['kod'], $zadost['cislo']);
            $this->stav($pocitac);

            $poZavreni = $this->poRestartuProhlizece($pocitac);
            self::assertSame($trvale ? $idUzivatele : null, $this->idPrihlasenehoNa($poZavreni));
        }
    }

    /**
     * Na obrazovce je jen jeden kód. Předchozí, který ještě nikdo neschválil, nový zamítne, stejně jako zrušení.
     */
    public function testNovyKodIZruseniZamitnePredchoziKod(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        $prvni = $this->zalozZadost($pocitac);
        $druha = $this->zalozZadost($pocitac);

        self::assertSame(PrihlaseniQrKodem::NEPLATNY_KOD, $this->schval($telefon, $prvni['kod'], $prvni['cislo']));

        $this->pozadavek($pocitac, static fn () => PrihlaseniQrKodem::zSession(false)->zrus());

        self::assertSame(PrihlaseniQrKodem::NEPLATNY_KOD, $this->schval($telefon, $druha['kod'], $druha['cislo']));
        self::assertSame(PrihlaseniQrKodem::STAV_ZADNA, $this->stav($pocitac));
    }

    public function testZamitnutiNaTelefonu(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        $zadost = $this->zalozZadost($pocitac);

        self::assertTrue($this->pozadavek($telefon, static fn () => PrihlaseniQrKodem::zamitni($zadost['kod'], $idUzivatele)));

        self::assertSame(PrihlaseniQrKodem::STAV_ZAMITNUTO, $this->stav($pocitac));
        self::assertSame(PrihlaseniQrKodem::NEPLATNY_KOD, $this->schval($telefon, $zadost['kod'], $zadost['cislo']));
    }

    /**
     * Přihlášený jinak (heslem v jiné kartě) žádost nepotřebuje, stránka s kódem se má obnovit.
     */
    public function testPrihlasenyProhlizecZadostZapomene(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        $zadost = $this->zalozZadost($pocitac);

        $stav = $this->pozadavek($pocitac, static fn () => PrihlaseniQrKodem::zSession(false)->zjistiStav(true));

        self::assertSame(PrihlaseniQrKodem::STAV_PRIHLASENO, $stav);
        self::assertNull((new PrihlaseniQrKodem($pocitac['session'], false))->getZadost());
        self::assertSame(PrihlaseniQrKodem::NEPLATNY_KOD, $this->schval($telefon, $zadost['kod'], $zadost['cislo']));
    }

    /**
     * Kdo narazí na limit, zůstane mu kód, který má: odmítnutý nový kód ho nezamítne.
     */
    public function testLimitZadostiZJednohoRozsahuIp(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        // jiná adresa ze stejné sítě /64
        $posledni = $this->zarizeni();
        $jehoZadost = $this->zalozZadost($posledni, ip: '2001:db8:1:2::99');
        $ip = '2001:db8:1:2::10';
        for ($i = 1; $i < PrihlaseniQrKodem::MAX_ZADOSTI_Z_IP; ++$i) {
            $zarizeni = $this->zarizeni();
            self::assertNull($this->zaloz($zarizeni, $ip));
        }

        self::assertNotNull($this->zaloz($posledni, '2001:db8:1:2::99'));
        self::assertSame(PrihlaseniQrKodem::MAX_ZADOSTI_Z_IP, (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM prihlaseni_qr WHERE ip_rozsah = '2001:db8:1:2::'
            SQL), 'Odmítnutá žádost se nemá zapsat.');
        self::assertSame($jehoZadost, (new PrihlaseniQrKodem($posledni['session'], false))->getZadost());
        self::assertSame(PrihlaseniQrKodem::SCHVALENO, $this->schval($telefon, $jehoZadost['kod'], $jehoZadost['cislo']));
        $jinaSit = $this->zarizeni();
        self::assertNull($this->zaloz($jinaSit, '2001:db8:1:3::10'));
    }

    /**
     * Opakované „Nový kód“ jednoho prohlížeče nevyčerpá limit celé sítě (wifi na festivalu). Kód, který má,
     * mu zůstane.
     */
    public function testLimitZadostiZJedneSession(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        for ($i = 0; $i < PrihlaseniQrKodem::MAX_ZADOSTI_ZE_SESSION; ++$i) {
            $zadost = $this->zalozZadost($pocitac);
        }
        $pocetZadosti = (int) dbOneCol('SELECT COUNT(*) FROM prihlaseni_qr');

        self::assertNotNull($this->zaloz($pocitac, self::IP));
        self::assertSame($pocetZadosti, (int) dbOneCol('SELECT COUNT(*) FROM prihlaseni_qr'), 'Odmítnutá žádost se nemá zapsat.');
        self::assertSame($zadost, (new PrihlaseniQrKodem($pocitac['session'], false))->getZadost());
        self::assertSame(PrihlaseniQrKodem::SCHVALENO, $this->schval($telefon, $zadost['kod'], $zadost['cislo']));
        $jinyProhlizec = $this->zarizeni();
        self::assertNull($this->zaloz($jinyProhlizec, self::IP));
    }

    /**
     * Session je pro web i admin společná, žádost jednoho z nich ale druhý neukáže ani nedokončí: trvalé přihlášení
     * dává cookie jen hostu, kde se zařízení přihlásí, a „Trvale přihlásit“ má na webu jinou výchozí volbu než v adminu.
     */
    public function testZadostWebuNedokonciAdminANaopak(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        $zadostWebu = $this->zalozZadost($pocitac, true);

        self::assertNull((new PrihlaseniQrKodem($pocitac['session'], true))->getZadost());
        $this->schval($telefon, $zadostWebu['kod'], $zadostWebu['cislo']);
        self::assertSame(PrihlaseniQrKodem::STAV_ZADNA, $this->stav($pocitac, doAdminu: true));
        self::assertNull($this->idPrihlasenehoNa($pocitac));
        self::assertSame(PrihlaseniQrKodem::STAV_PRIHLASENO, $this->stav($pocitac));

        $notebook = $this->zarizeni();
        $zadostAdminu = $this->zalozZadost($notebook, doAdminu: true);
        $this->schval($telefon, $zadostAdminu['kod'], $zadostAdminu['cislo']);
        // podvržená session, která by měla id žádosti adminu pod klíčem webu
        $podvrzena = $this->zarizeni(session: [
            'prihlaseni_qr' => $zadostAdminu,
        ]);
        self::assertSame(PrihlaseniQrKodem::STAV_VYPRSELO, $this->stav($podvrzena));
        self::assertNull($this->idPrihlasenehoNa($podvrzena));
        self::assertSame(PrihlaseniQrKodem::STAV_PRIHLASENO, $this->stav($notebook, doAdminu: true));
        self::assertSame($idUzivatele, $this->idPrihlasenehoNa($notebook));
    }

    /**
     * Vypršelý nebo zamítnutý kód přihlašovací stránka zapomene a ukáže formulář s heslem, i když se mezitím
     * nikdo neptal na stav (karta zavřená, návrat za hodinu).
     */
    public function testMrtvouZadostStrankaZapomene(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $telefon = $this->prihlaseneZarizeni($idUzivatele);
        $pocitac = $this->zarizeni();
        $zadost = $this->zalozZadost($pocitac);

        self::assertSame($zadost, $this->zivaZadost($pocitac));
        $this->posunVznikDoMinulosti($zadost['id'], PrihlaseniQrKodem::PLATNOST_SEKUND + 1);
        self::assertNull($this->zivaZadost($pocitac));
        self::assertNull((new PrihlaseniQrKodem($pocitac['session'], false))->getZadost());

        $zamitnuta = $this->zalozZadost($pocitac);
        $this->pozadavek($telefon, static fn () => PrihlaseniQrKodem::zamitni($zamitnuta['kod'], $idUzivatele));
        self::assertSame(PrihlaseniQrKodem::STAV_ZAMITNUTO, $this->stav($pocitac));
        self::assertNull((new PrihlaseniQrKodem($pocitac['session'], false))->getZadost(), 'Zamítnutou žádost má zapomenout i dotaz na stav.');
        self::assertSame(PrihlaseniQrKodem::STAV_ZADNA, $this->stav($pocitac));

        // schválenou, kterou zařízení ještě neuplatnilo, stránka ukazuje dál
        $schvalena = $this->zalozZadost($pocitac);
        $this->schval($telefon, $schvalena['kod'], $schvalena['cislo']);
        self::assertSame($schvalena, $this->zivaZadost($pocitac));
    }

    public function testStrankaSchvaleniUkazeZarizeniACisla(): void
    {
        $pocitac = $this->zarizeni();
        $zadost = $this->zalozZadost($pocitac);

        $proSchvaleni = PrihlaseniQrKodem::najdiProSchvaleni($zadost['kod']);

        self::assertSame(PrihlaseniQrKodem::STAV_CEKA, $proSchvaleni['stav']);
        self::assertSame('Chrome, Windows', $proSchvaleni['prohlizec']);
        self::assertSame(self::IP, $proSchvaleni['ip']);
        self::assertFalse($proSchvaleni['do_adminu']);
        self::assertContains($zadost['cislo'], $proSchvaleni['cisla']);
        self::assertNull(PrihlaseniQrKodem::najdiProSchvaleni('neexistujici-kod-xxxxx'));
        self::assertNull(PrihlaseniQrKodem::najdiProSchvaleni('0'));
    }

    /**
     * Kdyby se čísla s každým načtením stránky měnila, správné by prozradilo to, které zůstává. Kdyby šla odvodit
     * z kódu, prozradil by ho kód: stejné číslo u různých žádostí proto dostane různé nabídky.
     */
    public function testNabidkaCiselJeProZadostStalaASeSpravnymCislem(): void
    {
        $nabidky = [];
        for ($i = 0; $i < 20; ++$i) {
            $pocitac = $this->zarizeni();
            $zadost = $this->zalozZadost($pocitac);
            $cisla = PrihlaseniQrKodem::najdiProSchvaleni($zadost['kod'])['cisla'];

            self::assertCount(3, $cisla);
            self::assertCount(3, array_unique($cisla));
            self::assertContains($zadost['cislo'], $cisla);
            foreach ($cisla as $nabizene) {
                self::assertGreaterThanOrEqual(10, $nabizene);
                self::assertLessThanOrEqual(99, $nabizene);
            }
            self::assertSame($cisla, PrihlaseniQrKodem::najdiProSchvaleni($zadost['kod'])['cisla']);
            $nabidky[] = implode(',', $cisla);
        }
        self::assertGreaterThan(1, count(array_unique($nabidky)));
    }

    /**
     * Hodiny databáze, které skočí zpět (oprava přes NTP), nesmí stránku schválení shodit: rozdíl dvou čísel bez
     * znaménka, který vyjde záporný, MariaDB odmítne chybou.
     */
    public function testKodZBudoucnostiUkazeStrankaSchvaleni(): void
    {
        $pocitac = $this->zarizeni();
        $zadost = $this->zalozZadost($pocitac);
        $this->posunVznikDoMinulosti($zadost['id'], -30);

        $proSchvaleni = PrihlaseniQrKodem::najdiProSchvaleni($zadost['kod']);

        self::assertSame(0, $proSchvaleni['pred_sekundami']);
        self::assertSame(PrihlaseniQrKodem::STAV_CEKA, $proSchvaleni['stav']);
    }

    public function testNavratPoPrihlaseniJenNaStrankuSchvaleni(): void
    {
        self::assertSame(
            URL_WEBU . '/prihlasit-zarizeni?kod=abcdefghijklmnopqrstuv',
            PrihlaseniQrKodem::getUrlSchvaleniZParametru('abcdefghijklmnopqrstuv'),
        );
        foreach ([null, '', 'https://example.com', '//example.com', 'abcdefghijklmnopqrstu', ['a']] as $jinyParametr) {
            self::assertNull(PrihlaseniQrKodem::getUrlSchvaleniZParametru($jinyParametr));
        }
    }

    public function testPopisProhlizece(): void
    {
        $popisy = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1' => 'Safari, iOS',
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Mobile Safari/537.36'                       => 'Chrome, Android',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0'                                                        => 'Firefox, Windows',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36 Edg/131.0'             => 'Edge, macOS',
            'curl/8.5.0'                                                                                                                              => 'neznámý prohlížeč',
        ];
        foreach ($popisy as $userAgent => $popis) {
            self::assertSame($popis, PrihlaseniQrKodem::getPopisProhlizece($userAgent), $userAgent);
        }
    }

    /**
     * @return array{id: int, kod: string, cislo: int, trvale: bool}
     */
    private function zalozZadost(array &$zarizeni, bool $trvale = false, bool $doAdminu = false, string $ip = self::IP): array
    {
        self::assertNull($this->zaloz($zarizeni, $ip, $trvale, $doAdminu));
        $zadost = (new PrihlaseniQrKodem($zarizeni['session'], $doAdminu))->getZadost();
        self::assertNotNull($zadost);

        return $zadost;
    }

    /**
     * @return string|null proč žádost nevznikla, null když vznikla
     */
    private function zaloz(array &$zarizeni, string $ip, bool $trvale = false, bool $doAdminu = false): ?string
    {
        return $this->pozadavek(
            $zarizeni,
            static fn () => PrihlaseniQrKodem::zSession($doAdminu)->zaloz($ip, self::USER_AGENT, $trvale),
        );
    }

    /**
     * Jako přihlašovací stránka: žádost, kterou ukáže místo formuláře s heslem.
     *
     * @return array{id: int, kod: string, cislo: int, trvale: bool}|null
     */
    private function zivaZadost(array &$zarizeni): ?array
    {
        return $this->pozadavek($zarizeni, static fn () => PrihlaseniQrKodem::zSession(false)->getZivouZadost());
    }

    private function prihlaseneZarizeni(int $idUzivatele): array
    {
        $zarizeni = $this->zarizeni();
        $login = $this->getLogin($idUzivatele);
        $this->pozadavek($zarizeni, static fn () => \Uzivatel::prihlas($login, self::HESLO));

        return $zarizeni;
    }

    /**
     * Jako stránka schválení: za přihlášeného na telefonu, s razítkem jeho session.
     */
    private function schval(array &$telefon, string $kod, int $cislo): string
    {
        return $this->pozadavek($telefon, static function () use ($kod, $cislo): string {
            $u = \Uzivatel::zSession();
            self::assertNotNull($u, 'Telefon má být přihlášený, jinak test nic nezkouší.');

            return PrihlaseniQrKodem::schval($kod, $u->id(), \Uzivatel::getRazitkoPrihlaseniZSession(), $cislo);
        });
    }

    /**
     * Jako api/prihlaseniQr na webu, s $doAdminu jako api/prihlaseni-qr v adminu: na co se ptá přihlašovací
     * stránka zařízení.
     */
    private function stav(array &$zarizeni, bool $doAdminu = false): string
    {
        return $this->pozadavek($zarizeni, static function () use ($doAdminu): string {
            return PrihlaseniQrKodem::zSession($doAdminu)->zjistiStav(\Uzivatel::zSession() !== null);
        });
    }

    private function spatneCislo(string $kod, int $cislo): int
    {
        foreach (PrihlaseniQrKodem::najdiProSchvaleni($kod)['cisla'] as $nabizene) {
            if ($nabizene !== $cislo) {
                return $nabizene;
            }
        }
        self::fail('Nabídka má mít i špatná čísla.');
    }

    private function posunVznikDoMinulosti(int $idZadosti, int $sekund): void
    {
        dbQuery(<<<'SQL'
            UPDATE prihlaseni_qr SET vytvoreno_unix = vytvoreno_unix - $1 WHERE id = $0
            SQL, [
            0 => $idZadosti,
            1 => $sekund,
        ]);
    }
}
