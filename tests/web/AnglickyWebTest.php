<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Aktivita\JazykAktivity;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DatumVJazyce;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Jazyk\JazykPozadavku;

/**
 * Anglická verze webu přes skutečný Apache: prefix /en/, přepínač, zapamatování volby a soubory pod prefixem.
 */
class AnglickyWebTest extends AbstractTestWeb
{
    /**
     * Stránka z CMS, která se nepřekládá: pod /en/ má anglické jen menu a patičku.
     */
    private const string NEPRELOZENA_STRANKA = 'neprelozena-testovaci-stranka';

    private const string CESKA_AKTIVITA = 'Česká testovací aktivita';
    private const string ANGLICKA_AKTIVITA = 'English test activity';
    private const string ZACATEK_AKTIVIT = '-07-17 10:00:00';

    // AbstractTestWeb zakládá role mimo transakci, další webový test by je zakládal znovu
    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            // stará ručně psaná anglická titulka, nesmí být k vidění
            "INSERT INTO stranky (url_stranky, obsah) VALUES ('en', '# Welcome to GameCon test page')",
            "INSERT INTO stranky (url_stranky, obsah) VALUES ('" . self::NEPRELOZENA_STRANKA . "', '# Nepřeložená stránka')",
            // aktivity na stránce linie, česká s kapacitou, anglická neomezená
            self::vlozeniAktivity(9871, self::CESKA_AKTIVITA, 10, JazykAktivity::CESKY),
            self::vlozeniAktivity(9872, self::ANGLICKA_AKTIVITA, 0, JazykAktivity::ANGLICKY),
            'INSERT INTO akce_organizatori (id_akce, id_uzivatele) VALUES (9871, ' . \Uzivatel::SYSTEM . '), (9872, ' . \Uzivatel::SYSTEM . ')',
        ];
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private static function vlozeniAktivity(int $id, string $nazev, int $kapacita, JazykAktivity $jazyk): array
    {
        return [
            <<<'SQL'
INSERT INTO akce_seznam (id_akce, nazev_akce, kapacita, kapacita_f, kapacita_m, cena, bez_slevy, nedava_bonus, typ, rok, stav, teamova, popis, popis_kratky, vybaveni, zacatek, konec, jazyk)
VALUES ($0, $1, $2, 0, 0, 100, 0, 0, $3, $4, $5, 0, '', $6, '', $7, $7 + INTERVAL 2 HOUR, $8)
SQL,
            [
                0 => $id,
                1 => $nazev,
                2 => $kapacita,
                3 => TypAktivity::LARP,
                4 => ROCNIK,
                5 => StavAktivity::PUBLIKOVANA,
                6 => 'Krátký popis: ' . $nazev,
                7 => ROCNIK . self::ZACATEK_AKTIVIT,
                8 => $jazyk->value,
            ],
        ];
    }

    public function testAnglickaTitulkaJePrelozenaTitulka(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/en/');

        self::assertSame(200, $kod);
        foreach ([
            '<div class="gchlavicka_claim" data-aos="zoom-out">The largest festival of non-computer games</div>',
            '<h2>Something for everyone</h2>',
            '<a href="deskoherna">Board games</a>, <a href="rpg">RPGs</a> and <a href="larpy">larps</a>,',
            'a <a href="dnd">D&amp;D tournament</a>, movement <a href="bonusy">workshops</a>',
            '<img alt="participants icon"',
            '<div class="zazijes_nazevlinie">Larps</div>',
            '<h2 class="zazijes_nadpis">What’s waiting for you at GameCon?</h2>',
            '<a href="sponzori">Thank you to our sponsors and partners!</a>',
        ] as $text) {
            self::assertStringContainsString($text, $telo);
        }
        // před názvem bývá prefix prostředí (Info::pridejPrefixPodleVyvoje())
        self::assertMatchesRegularExpression('~<title>[^<]*GameCon – the largest festival of non-computer games</title>~', $telo);
        self::assertMatchesRegularExpression(
            '~<div class="gchlavicka_datum"[^>]*>\d{1,2} &ndash; \d{1,2} July <span~',
            $telo,
            'Datum s anglickým názvem měsíce, bez českých teček.',
        );
        self::assertStringNotContainsString('Welcome to GameCon test page', $telo, 'Zastaralá stránka en z CMS se už neukazuje.');
        self::assertDoesNotMatchRegularExpression(
            '~[ěščřžýáíéóúůťďňĚŠČŘŽÝÁÍÉÓÚŮŤĎŇ]~u',
            $this->hlavniObsah($telo),
            'V obsahu anglické titulky nezůstal český text (skripty a styly se nepočítají).',
        );
    }

    public function testStrankaEnPodAnglictinouPresmerujeNaAnglickouTitulku(): void
    {
        foreach (['web/en/en', 'web/EN'] as $cesta) {
            ['kod' => $kod, 'hlavicky' => $hlavicky] = $this->stahni($cesta);

            self::assertSame(301, $kod, "{$cesta} by byla kopií anglické titulky");
            self::assertMatchesRegularExpression('~^Location: ' . preg_quote(URL_WEBU . '/en/', '~') . '\r?$~m', $hlavicky, $cesta);
        }
    }

    public function testAnglickaTitulkaMaAnglickeMenuAOdkazyNaJazyky(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/en/');

        self::assertSame(200, $kod);
        self::assertStringContainsString('<html lang="en">', $telo);
        self::assertStringContainsString('<base href="' . URL_WEBU . '/en/">', $telo);
        self::assertStringContainsString('<meta property="og:locale" content="en_GB">', $telo);
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/en/">', $telo);
        self::assertStringContainsString('hreflang="cs" href="' . URL_WEBU . '/"', $telo);
        self::assertStringContainsString('hreflang="en" href="' . URL_WEBU . '/en/"', $telo);
        self::assertStringContainsString('hreflang="x-default" href="' . URL_WEBU . '/"', $telo);
        self::assertStringNotContainsString('<div lang="cs">', $telo, 'Anglická titulka je přeložená, obsah není česky.');
        self::assertStringContainsString('>programme</a>', $telo);
        self::assertStringContainsString('<a href="larpy" class="menu_polozka">larps</a>', $telo, 'Linie v menu anglicky');
        self::assertStringContainsString('href="' . URL_WEBU . '/?jazyk=cs"', $telo, 'Přepínač do češtiny');
    }

    public function testCeskaTitulkaZustavaCesky(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/');

        self::assertSame(200, $kod);
        self::assertStringContainsString('<html lang="cs">', $telo);
        self::assertStringContainsString('>program</a>', $telo);
        self::assertStringContainsString('href="' . URL_WEBU . '/en/?jazyk=en"', $telo, 'Přepínač do angličtiny');
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/">', $telo);
        self::assertStringContainsString('hreflang="en" href="' . URL_WEBU . '/en/"', $telo, 'Titulka má anglickou verzi.');
        self::assertStringContainsString('<meta property="og:locale" content="cs_CZ">', $telo);
        foreach ([
            '<div class="gchlavicka_claim" data-aos="zoom-out">Největší festival nepočítačových her</div>',
            '<h2>Akce pro všechny</h2>',
            // věta z katalogu s odkazy dosazenými až po escapování
            '<a href="deskoherna">Deskové</a> hry, <a href="rpg">RPG</a> a <a href="larpy">Larpy</a>, <a href="bonusy">akční hry</a>, <a href="wargaming">wargaming</a>, <a href="dnd">Turnaj</a> v D&amp;D/JaD, pohybové <a href="bonusy">workshopy</a>, koncert a párty. To všechno a mnohem víc je festival nepočítačových her GameCon.',
            '<img alt="ikona účastníků"',
            '<div class="zazijes_nazevlinie">Larpy</div>',
            '<h2 class="zazijes_nadpis">Co na GameConu zažiješ?</h2>',
            '<a href="larpy" class="menu_polozka">larpy</a>',
        ] as $text) {
            self::assertStringContainsString($text, $telo);
        }
        self::assertMatchesRegularExpression('~<div class="gchlavicka_datum"[^>]*>\d{1,2}\. &ndash; \d{1,2}\. 7\. <span~', $telo);
    }

    public function testStrankaLiniePodAnglictinouMaAnglickeRozhrani(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/en/larpy');

        self::assertSame(200, $kod);
        self::assertMatchesRegularExpression('~<title>[^<]*Larps</title>~', $telo, 'Název linie anglicky');
        $zacatek = new \DateTimeImmutable(ROCNIK . self::ZACATEK_AKTIVIT);
        foreach ([
            '<b>Storyteller:</b>',
            '>show more</label>',
            '<h3>Details:</h3>',
            '<h3>Times:</h3>',
            '<div class="programNahled_obsazenost aktivity_nahledInfo">unlimited</div>',
            '<b class="aktivity_terminCas">' . DatumVJazyce::denVTydnu($zacatek, Jazyk::ANGLICTINA) . ' 10:00–12:00</b>',
            '<a href="larpy" class="menu_polozka">larps</a>',
        ] as $text) {
            self::assertStringContainsString($text, $telo);
        }
        foreach (['Vypravěč:', 'zobrazit více', 'Termíny:', 'neomezeně'] as $cesky) {
            self::assertStringNotContainsString($cesky, $telo);
        }
        // název a popis aktivity píše vypravěč, jazyk mu dá Aktivita::jazyk(), rozhraní kolem je v jazyce stránky
        self::assertStringContainsString('<h2 class="aktivity_nazev" lang="cs">', $this->kartaAktivity($telo, self::CESKA_AKTIVITA));
        self::assertStringContainsString('<h2 class="aktivity_nazev" lang="en">', $this->kartaAktivity($telo, self::ANGLICKA_AKTIVITA));
        self::assertStringContainsString('<span lang="en">Krátký popis: ' . self::ANGLICKA_AKTIVITA . '</span>', $telo);
        self::assertStringContainsString('<h2 lang="en">' . self::ANGLICKA_AKTIVITA . '</h2>', $telo, 'Náhled aktivity');
    }

    public function testStrankaLinieVCestineZustavaCesky(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/larpy');

        self::assertSame(200, $kod);
        $zacatek = new \DateTimeImmutable(ROCNIK . self::ZACATEK_AKTIVIT);
        foreach ([
            '<b>Vypravěč:</b>',
            '>zobrazit více</label>',
            '<h3>Informace:</h3>',
            '<h3>Termíny:</h3>',
            '<div class="programNahled_obsazenost aktivity_nahledInfo">neomezeně</div>',
            '<b class="aktivity_terminCas">' . DatumVJazyce::denVTydnu($zacatek, Jazyk::CESTINA) . ' 10–12</b>',
        ] as $text) {
            self::assertStringContainsString($text, $telo);
        }
    }

    public function testNeprelozenaStrankaPodAnglictinouJeKanonickyCeska(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/en/' . self::NEPRELOZENA_STRANKA);

        self::assertSame(200, $kod);
        self::assertStringContainsString('<html lang="en">', $telo, 'Menu a patička jsou anglicky.');
        self::assertStringContainsString('<div lang="cs">', $telo, 'Obsah je česky.');
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/' . self::NEPRELOZENA_STRANKA . '">', $telo);
        self::assertStringContainsString('<meta property="og:url" content="' . URL_WEBU . '/' . self::NEPRELOZENA_STRANKA . '">', $telo);
        self::assertStringContainsString('<meta property="og:locale" content="cs_CZ">', $telo);
        self::assertStringNotContainsString('<link rel="alternate" hreflang', $telo, 'Český obsah pod /en/ se vyhledávačům nehlásí jako anglická verze.');
    }

    public function testNeprelozenaCeskaStrankaNemaJazykoveVerze(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/' . self::NEPRELOZENA_STRANKA);

        self::assertSame(200, $kod);
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/' . self::NEPRELOZENA_STRANKA . '">', $telo);
        self::assertStringNotContainsString('<link rel="alternate" hreflang', $telo);
        self::assertStringNotContainsString('<div lang=', $telo, 'Obsah je v jazyce stránky.');
    }

    public function testNenalezenaStrankaNemaJazykoveVerze(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/en/tahle-stranka-neexistuje');

        self::assertSame(404, $kod);
        self::assertStringNotContainsString('<link rel="alternate" hreflang', $telo);
    }

    public function testKdoSiZvolilAnglictinuPrijdeZCeskeAdresyNaAnglickou(): void
    {
        ['kod' => $kod, 'hlavicky' => $hlavicky] = $this->stahni('web/program', JazykPozadavku::COOKIE . '=en');

        self::assertSame(302, $kod);
        self::assertMatchesRegularExpression('~^Location: ' . preg_quote(URL_WEBU . '/en/program', '~') . '\r?$~m', $hlavicky);
    }

    public function testFetchSeNepresmerovava(): void
    {
        ['kod' => $kod] = $this->stahni(
            'web/' . self::NEPRELOZENA_STRANKA,
            JazykPozadavku::COOKIE . '=en',
            ['Sec-Fetch-Mode: cors'],
        );

        self::assertSame(200, $kod, 'fetch() (třeba výzva Altchy) není navigace, přesměrovat se nemá.');
    }

    public function testNavigaceNaAnglickouAdresuSiZapamatujeVolbu(): void
    {
        ['hlavicky' => $hlavicky] = $this->stahni('web/en/' . self::NEPRELOZENA_STRANKA, '', ['Sec-Fetch-Mode: navigate']);

        self::assertMatchesRegularExpression('~^Set-Cookie: ' . JazykPozadavku::COOKIE . '=en;~mi', $hlavicky);
    }

    public function testAnglickaAdresaMimoNavigaciVolbuNemeni(): void
    {
        ['hlavicky' => $fetch] = $this->stahni('web/en/' . self::NEPRELOZENA_STRANKA, JazykPozadavku::COOKIE . '=cs', ['Sec-Fetch-Mode: cors']);
        self::assertStringNotContainsString(JazykPozadavku::COOKIE . '=', $fetch, 'fetch()');

        ['hlavicky' => $api] = $this->stahni('web/en/api/prihlasenyUzivatel', JazykPozadavku::COOKIE . '=cs');
        self::assertStringNotContainsString(JazykPozadavku::COOKIE . '=', $api, 'Stará anglická záložka volající API nesmí přepnout volbu zpět.');

        ['kod' => $kodFormulare, 'hlavicky' => $formular] = $this->stahni(
            'web/en/' . self::NEPRELOZENA_STRANKA,
            JazykPozadavku::COOKIE . '=cs',
            ['Referer: ' . URL_WEBU . '/en/' . self::NEPRELOZENA_STRANKA],
            post: true,
        );
        self::assertSame(200, $kodFormulare, 'Formulář musí projít ochranou proti CSRF, jinak test nic neověří.');
        self::assertStringNotContainsString(JazykPozadavku::COOKIE . '=', $formular, 'formulář');
    }

    public function testPrepinacUloziVolbuAPresmerujeBezParametru(): void
    {
        ['kod' => $kod, 'hlavicky' => $hlavicky] = $this->stahni('web/en/program?jazyk=cs', JazykPozadavku::COOKIE . '=en');

        self::assertSame(302, $kod);
        self::assertMatchesRegularExpression('~^Location: ' . preg_quote(URL_WEBU . '/program', '~') . '\r?$~m', $hlavicky);
        self::assertStringContainsString('Set-Cookie: ' . JazykPozadavku::COOKIE . '=cs', $hlavicky);
    }

    public function testSouborPodAnglickymPrefixemJeStejnySoubor(): void
    {
        ['kod' => $kod, 'hlavicky' => $hlavicky] = $this->stahni('web/en/soubory/blackarrow/_spolecne/favicon.png');

        self::assertSame(200, $kod);
        self::assertMatchesRegularExpression('~^Content-Type: image/png~mi', $hlavicky);
    }

    public function testStrankaSymfonyPodAnglickymPrefixemFunguje(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/en/zapomenute-heslo');

        self::assertSame(200, $kod);
        self::assertStringContainsString('>programme</a>', $telo, 'Symfony stránka vykresluje menu webu, i to má být anglicky.');
        self::assertStringContainsString('<html lang="en">', $telo);
        self::assertStringContainsString('<base href="' . URL_WEBU . '/en/">', $telo, 'Relativní odkazy mají zůstat v angličtině.');
        self::assertStringContainsString('<div lang="cs">', $telo, 'Obnova hesla zatím přeložená není, obsah je česky.');
    }

    public function testCestaZUrlSeVPrepinaciJazykaNeprovede(): void
    {
        ['telo' => $telo] = $this->stahni('web/x%22%3E%3Cb%3Eahoj%3C/b%3E');

        self::assertStringNotContainsString('<b>ahoj</b>', $telo);
        self::assertStringContainsString('href="' . URL_WEBU . '/en/x%22%3E%3Cb%3Eahoj%3C/b%3E?jazyk=en"', $telo);
    }

    public function testApiPodAnglickymPrefixemFunguje(): void
    {
        ['kod' => $kod] = $this->stahni('web/en/api/prihlasenyUzivatel');

        self::assertSame(200, $kod);
    }

    /**
     * Obsah stránky mezi menu a patičkou, bez skriptů, stylů a komentářů (ty mají české poznámky pro vývojáře).
     */
    private function hlavniObsah(string $telo): string
    {
        $zacatek = strpos($telo, '<div class="gchlavicka">');
        $konec = strpos($telo, '<div class="paticka">');
        self::assertNotFalse($zacatek, 'Na stránce chybí hlavička titulky.');
        self::assertNotFalse($konec, 'Na stránce chybí patička.');

        return (string) preg_replace(
            ['~<script\b.*?</script>~s', '~<style\b.*?</style>~s', '~<!--.*?-->~s'],
            '',
            substr($telo, $zacatek, $konec - $zacatek),
        );
    }

    /**
     * Karta aktivity od nadpisu po termíny.
     */
    private function kartaAktivity(string $telo, string $nazev): string
    {
        $konecNazvu = strpos($telo, '>' . htmlspecialchars($nazev) . '</label>');
        self::assertNotFalse($konecNazvu, "Na stránce chybí aktivita {$nazev}");
        $zacatek = strrpos(substr($telo, 0, $konecNazvu), '<h2 ');
        self::assertNotFalse($zacatek);

        return substr($telo, $zacatek, $konecNazvu - $zacatek);
    }

    /**
     * @param list<string> $hlavicky další hlavičky požadavku
     *
     * @return array{kod: int, hlavicky: string, telo: string}
     */
    private function stahni(string $cesta, string $cookie = '', array $hlavicky = [], bool $post = false): array
    {
        get_headers(URL_WEBU); // DNS a Apache připravené dřív než curl, viz StrankyWebuTest

        $curl = curl_init('http://localhost/' . $cesta);
        self::assertNotFalse($curl);
        $this->setLocalServerCookieWithGameconTestDb($curl);
        if ($cookie !== '') {
            curl_setopt($curl, CURLOPT_COOKIE, 'gamecon_test_db=' . DB_NAME . '; unit_tests=1; ' . $cookie);
        }
        if ($hlavicky !== []) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $hlavicky);
        }
        if ($post) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, '');
        }
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        $odpoved = (string) curl_exec($curl);
        $kod = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $velikostHlavicek = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        return [
            'kod'      => $kod,
            'hlavicky' => substr($odpoved, 0, $velikostHlavicek),
            'telo'     => substr($odpoved, $velikostHlavicek),
        ];
    }
}
