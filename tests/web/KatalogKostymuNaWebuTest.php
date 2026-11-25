<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;

/**
 * Veřejný katalog kostýmů přes skutečný Apache: zveřejnění, vyřazené kostýmy, kanonické adresy a stránka
 * pro přihlášené.
 */
class KatalogKostymuNaWebuTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const int ID_UCASTNIKA = 9871;
    private const string LOGIN_UCASTNIKA = 'test-kostymy-ucastnik';

    /**
     * larp za hodinu: po uzávěrce (2 h před začátkem), ale ještě nezačal; účastník na něj má Elfí plášť
     */
    private const int ID_LARPU_ZA_HODINU = 9871;

    /**
     * larp, který přišel o čas; účastník na něj má Klobouk
     */
    private const int ID_LARPU_BEZ_CASU = 9872;

    /**
     * larp zítra, daleko před uzávěrkou; účastník na něj má Šátek
     */
    private const int ID_LARPU_ZITRA = 9873;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    // Apache musí vidět zveřejnění katalogu, které test mění, takže bez transakce testovací metody.
    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    /**
     * @return array<int, string|array{0: string, 1: array<int, mixed>}>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return array_merge(
            parent::getSetUpBeforeClassInitQueries(),
            [
                <<<'SQL'
INSERT INTO kostym (id, nazev, popis, velikost, miry, strih, kategorie, stav, interni_poznamka)
VALUES (901, 'Elfí plášť', 'Zelený', 'm', '', 'unisex', 'Fantasy', 'k_dispozici', 'skříň 3'),
       (902, 'Rytířská zbroj', '', 'l', '', 'pansky', 'Historie', 'v_oprave', ''),
       (903, 'Roztrhaný kabát', '', 'xl', '', 'pansky', 'Historie', 'vyrazeny', ''),
       (904, 'Klobouk', '', 'm', '', 'unisex', 'Historie', 'k_dispozici', ''),
       (905, 'Šerpa', '', 'm', '', 'unisex', 'Historie', 'k_dispozici', ''),
       (906, 'Šátek', '', 'm', '', 'unisex', 'Historie', 'k_dispozici', '')
SQL,
                [
                    <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES ($0, $1, 'Test', 'Kostýmy', 'test-kostymy-ucastnik@example.invalid')
SQL,
                    [self::ID_UCASTNIKA, self::LOGIN_UCASTNIKA],
                ],
                [
                    // Apache bere „teď“ ze skutečných hodin, a tak i začátek larpu
                    <<<'SQL'
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m,
                        teamova, zacatek, konec)
VALUES ($0, 'Larp za hodinu', 'test-kostymy-larp-za-hodinu', $2, $3, 1, 'Popis', 0, 10, 0, 0, 0, $4, $5),
       ($1, 'Larp bez času', 'test-kostymy-larp-bez-casu', $2, $3, 1, 'Popis', 0, 10, 0, 0, 0, NULL, NULL),
       ($6, 'Larp zítra', 'test-kostymy-larp-zitra', $2, $3, 1, 'Popis', 0, 10, 0, 0, 0, $7, $8)
SQL,
                    [
                        self::ID_LARPU_ZA_HODINU,
                        self::ID_LARPU_BEZ_CASU,
                        TypAktivity::LARP,
                        ROCNIK,
                        date('Y-m-d H:i:s', time() + 3600),
                        date('Y-m-d H:i:s', time() + 3 * 3600),
                        self::ID_LARPU_ZITRA,
                        date('Y-m-d H:i:s', time() + 24 * 3600),
                        date('Y-m-d H:i:s', time() + 26 * 3600),
                    ],
                ],
                [
                    <<<'SQL'
INSERT INTO akce_prihlaseni(id_akce, id_uzivatele, id_stavu_prihlaseni) VALUES ($0, $2, $3), ($1, $2, $3), ($4, $2, $3)
SQL,
                    [self::ID_LARPU_ZA_HODINU, self::ID_LARPU_BEZ_CASU, self::ID_UCASTNIKA, StavPrihlaseni::PRIHLASEN, self::ID_LARPU_ZITRA],
                ],
                [
                    <<<'SQL'
INSERT INTO kostym_rezervace(id_kostymu, id_akce, id_uzivatele, rezervovano)
VALUES (901, $0, $2, NOW()), (904, $1, $2, NOW()), (906, $3, $2, NOW())
SQL,
                    [self::ID_LARPU_ZA_HODINU, self::ID_LARPU_BEZ_CASU, self::ID_UCASTNIKA, self::ID_LARPU_ZITRA],
                ],
            ],
        );
    }

    private static function zverejniKatalog(bool $zverejnit): void
    {
        // bez `vlastni` platí výchozí hodnota, tedy nezveřejněný
        dbQuery(<<<'SQL'
UPDATE systemove_nastaveni SET hodnota = $0, vlastni = 1 WHERE klic = 'KOSTYMY_KATALOG_ZVEREJNEN'
SQL, [$zverejnit ? '1' : '0']);
    }

    protected function tearDown(): void
    {
        self::zverejniKatalog(false);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function nezverejnenyKatalogNeexistuje(): void
    {
        self::zverejniKatalog(false);

        self::assertSame(404, $this->stahniVerejnouStranku('web/kostymy')['status']);
        self::assertSame(404, $this->stahniVerejnouStranku('web/kostymy/901')['status']);
    }

    /**
     * @test
     */
    public function zverejnenyKatalogUkazeKostymyKromeVyrazenych(): void
    {
        self::zverejniKatalog(true);

        $odpoved = $this->stahniVerejnouStranku('web/kostymy?velikost=&strih=&kategorie=');

        self::assertSame(200, $odpoved['status']);
        self::assertStringContainsString('Elfí plášť', $odpoved['telo']);
        self::assertStringContainsString('Rytířská zbroj', $odpoved['telo']);
        self::assertStringContainsString('teď v opravě', $odpoved['telo']);
        self::assertStringNotContainsString('Roztrhaný kabát', $odpoved['telo']);
        self::assertStringNotContainsString('skříň 3', $odpoved['telo'], 'Interní poznámka na web nepatří');
        self::assertStringEndsWith('/web/kostymy', $this->kanonickaAdresa($odpoved['telo']), 'Filtr neurčuje jinou stránku');
    }

    /**
     * @test
     */
    public function filtrPodleStrihu(): void
    {
        self::zverejniKatalog(true);

        $telo = $this->stahniVerejnouStranku('web/kostymy?strih=pansky')['telo'];

        self::assertStringContainsString('Rytířská zbroj', $telo);
        self::assertStringNotContainsString('Elfí plášť', $telo);
    }

    /**
     * @test
     */
    public function detailKostymuMaVlastniKanonickouAdresu(): void
    {
        self::zverejniKatalog(true);

        $odpoved = $this->stahniVerejnouStranku('web/kostymy/901?aktivita=5');

        self::assertSame(200, $odpoved['status']);
        self::assertStringContainsString('Zelený', $odpoved['telo']);
        self::assertStringContainsString('po <a href="prihlaseni">přihlášení</a>', $odpoved['telo']);
        self::assertStringEndsWith('/web/kostymy/901', $this->kanonickaAdresa($odpoved['telo']));
        self::assertSame(404, $this->stahniVerejnouStranku('web/kostymy/903')['status'], 'Vyřazený kostým na webu není');
        self::assertSame(404, $this->stahniVerejnouStranku('web/kostymy/nic')['status']);
    }

    /**
     * @test
     */
    public function vlastniRezervaceJsouJenProPrihlasene(): void
    {
        foreach ([true, false] as $zverejnit) {
            self::zverejniKatalog($zverejnit);

            self::assertSame(401, $this->stahniVerejnouStranku('web/kostymy/moje')['status']);
        }
    }

    /**
     * Správce rezervuje za účastníky i před zveřejněním a e-mail i program posílají na Moje kostýmy.
     *
     * @test
     */
    public function vlastniRezervaceVidiUcastnikIPredZverejnenim(): void
    {
        self::zverejniKatalog(false);
        $relace = $this->prihlas(self::LOGIN_UCASTNIKA);

        $telo = $this->stahniVerejnou($relace, $this->cestaWebu() . '/kostymy/moje');

        self::assertStringContainsString('Elfí plášť', $telo);
        self::assertStringContainsString('Larp za hodinu', $telo);
        self::assertStringNotContainsString('/kostymy/901', $telo, 'Detail kostýmu před zveřejněním neexistuje');
        self::assertStringNotContainsString('vidíš ho jen jako správce', $telo);
        self::assertStringContainsString('Zrušit ho můžeš do uzávěrky', $telo, 'Larp zítra jde zrušit');
        self::assertStringNotContainsString('Vyměnit za jiný', $telo, 'Vyměnit jde jen ve zveřejněném katalogu');
        self::assertStringNotContainsString('Zrušit nebo vyměnit ho můžeš', $telo);
    }

    /**
     * @test
     */
    public function rezervaceNaAktivituBezCasuNemaCasyAniUzaverku(): void
    {
        self::zverejniKatalog(true);
        $relace = $this->prihlas(self::LOGIN_UCASTNIKA);

        $telo = $this->stahniVerejnou($relace, $this->cestaWebu() . '/kostymy/moje');

        self::assertSame(1, preg_match('~<article class="kostymy_mojeRezervace">(?:(?!</article>).)*Klobouk(?:(?!</article>).)*</article>~s', $telo, $rezervace));
        self::assertStringContainsString('bez času', $rezervace[0]);
        self::assertStringNotContainsString('Vyzvednout od', $rezervace[0]);
        self::assertStringNotContainsString('Zrušit rezervaci', $rezervace[0]);
    }

    /**
     * @test
     */
    public function poUzaverceDetailNenabidneVymenu(): void
    {
        self::zverejniKatalog(true);
        $relace = $this->prihlas(self::LOGIN_UCASTNIKA);

        $telo = $this->stahniVerejnou($relace, $this->cestaWebu() . '/kostymy/905');

        self::assertStringContainsString('value="' . self::ID_LARPU_ZITRA . '"', $telo, 'Před uzávěrkou výměnu nabídne');
        self::assertStringNotContainsString('value="' . self::ID_LARPU_ZA_HODINU . '"', $telo);
        // Šátek má na larp zítra, na larp za hodinu už ho nevymění
        self::assertStringContainsString(
            'po uzávěrce vymění jen půjčovna',
            $this->stahniVerejnou($relace, $this->cestaWebu() . '/kostymy/906'),
        );

        $katalog = $this->stahniVerejnou($relace, $this->cestaWebu() . '/kostymy');
        self::assertStringContainsString('Larp zítra', $katalog, 'Před uzávěrkou filtr aktivitu nabízí');
        self::assertStringNotContainsString('Larp za hodinu', $katalog, 'Po uzávěrce by výměna neprošla');
    }

    /**
     * @test
     */
    public function spravceVNezverejnenemKataloguRezervaciNenabidne(): void
    {
        self::zverejniKatalog(false);
        $relace = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        $telo = $this->stahniVerejnou($relace, $this->cestaWebu() . '/kostymy/901');

        self::assertStringContainsString('vidíš ho jen jako správce', $telo);
        self::assertStringNotContainsString('value="rezervovat"', $telo);
    }

    /**
     * @test
     */
    public function rezervaceTehozKostymuNeslibujeEmail(): void
    {
        self::zverejniKatalog(true);
        $relace = $this->prihlas(self::LOGIN_UCASTNIKA);
        $adresaDetailu = 'http://localhost' . $this->cestaWebu() . '/kostymy/901';
        $curl = $this->curlNaAdresu($relace, $adresaDetailu);
        curl_setopt($curl, CURLOPT_POST, true);
        // POST bez odkazující stránky z téhož webu web zahodí jako CSRF
        curl_setopt($curl, CURLOPT_REFERER, $adresaDetailu);
        curl_setopt($curl, CURLOPT_POSTFIELDS, [
            'akceKostymu' => 'rezervovat',
            'idKostymu'   => 901,
            'idAktivity'  => self::ID_LARPU_ZA_HODINU,
        ]);
        $odpoved = $this->proved($curl);
        unset($curl); // až teď curl zapíše cookies s hláškou do souboru

        self::assertSame(303, $odpoved['kod'], $odpoved['telo']);
        $telo = $this->stahniVerejnou($relace, $this->cestaWebu() . '/kostymy/moje');
        self::assertStringContainsString('Tenhle kostým už na aktivitu rezervovaný máš.', $telo);
        self::assertStringNotContainsString('e-mailem', $telo);
    }

    private function cestaWebu(): string
    {
        return (string) parse_url(URL_WEBU, PHP_URL_PATH);
    }

    private function kanonickaAdresa(string $telo): string
    {
        self::assertSame(1, preg_match('~<link rel="canonical" href="([^"]+)">~', $telo, $shoda), 'Stránka nemá kanonickou adresu');

        return html_entity_decode($shoda[1]);
    }
}
