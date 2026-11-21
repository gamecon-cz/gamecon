<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

/**
 * Stránky z CMS s anglickým obsahem (`stranky.obsah_en`) přes skutečný Apache: přeložená stránka je pod /en/ anglicky
 * a má jazykové verze, nepřeložená ukáže český obsah s poznámkou a zůstává kanonicky česká. Editor v adminu má pro
 * anglický obsah vlastní pole.
 */
class AnglickeStrankyWebTest extends AbstractTestWeb
{
    private const string PRELOZENA = 'prelozena-testovaci-stranka';
    private const string NEPRELOZENA = 'neprelozena-testovaci-stranka';
    /**
     * podstránka linie RPG (url_typu_mn `rpg`), má odkaz zpět na linii
     */
    private const string PODSTRANKA_LINIE = 'rpg/testovaci-podstranka';
    private const int ID_UKLADANE = 9101;

    /**
     * @return array<int, string|array{0: string, 1: array<int, mixed>}>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            [
                <<<'SQL'
INSERT INTO stranky(id_stranky, url_stranky, obsah, obsah_en, poradi)
VALUES
    (NULL, $0, $1, $2, 0),
    (NULL, $3, $4, NULL, 0),
    (NULL, $5, $6, $7, 0),
    ($8, $9, $10, NULL, 0)
SQL,
                [
                    self::PRELOZENA,
                    "# Přeložená stránka\n\nŘekni \"ahoj\".",
                    "# Translated page\n\nSay \"hello\".",
                    self::NEPRELOZENA,
                    "# Nepřeložená stránka\n\nŘekni \"ahoj\".",
                    self::PODSTRANKA_LINIE,
                    '# Podstránka',
                    '# Subpage',
                    self::ID_UKLADANE,
                    'ukladana-testovaci-stranka',
                    '# Ukládaná stránka',
                ],
            ],
        ];
    }

    /**
     * @test
     */
    public function prelozenaStrankaJePodAnglictinouAnglickyAMaJazykoveVerze(): void
    {
        ['status' => $status, 'telo' => $telo] = $this->stahniVerejnouStranku('web/en/' . self::PRELOZENA);

        self::assertSame(200, $status);
        self::assertStringContainsString('<h1>Translated page</h1>', $telo);
        self::assertStringContainsString('Say “hello”.', $telo, 'Anglický obsah má anglické uvozovky.');
        self::assertStringNotContainsString('Přeložená stránka', $telo);
        self::assertStringNotContainsString('<div lang="cs">', $telo, 'Obsah je v jazyce stránky.');
        self::assertStringNotContainsString('stranka_jenCesky', $telo);
        // před názvem může být značka prostředí (na betě, v testech)
        self::assertMatchesRegularExpression('~<title>[^<]*Translated page</title>~u', $telo);
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/en/' . self::PRELOZENA . '">', $telo);
        self::assertStringContainsString('<meta property="og:locale" content="en_GB">', $telo);
        self::assertStringContainsString('hreflang="cs" href="' . URL_WEBU . '/' . self::PRELOZENA . '"', $telo);
        self::assertStringContainsString('hreflang="en" href="' . URL_WEBU . '/en/' . self::PRELOZENA . '"', $telo);
        self::assertStringContainsString('hreflang="x-default" href="' . URL_WEBU . '/' . self::PRELOZENA . '"', $telo);
    }

    /**
     * @test
     */
    public function prelozenaStrankaJeVCestineCeskaAOhlasujeAnglickouVerzi(): void
    {
        ['status' => $status, 'telo' => $telo] = $this->stahniVerejnouStranku('web/' . self::PRELOZENA);

        self::assertSame(200, $status);
        self::assertStringContainsString('<h1>Přeložená stránka</h1>', $telo);
        self::assertStringContainsString('Řekni „ahoj“.', $telo);
        self::assertStringNotContainsString('Translated page', $telo);
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/' . self::PRELOZENA . '">', $telo);
        self::assertStringContainsString('hreflang="en" href="' . URL_WEBU . '/en/' . self::PRELOZENA . '"', $telo);
    }

    /**
     * @test
     */
    public function neprelozenaStrankaJePodAnglictinouCeskySPoznamkouAKanonickyCeska(): void
    {
        ['status' => $status, 'telo' => $telo] = $this->stahniVerejnouStranku('web/en/' . self::NEPRELOZENA);

        self::assertSame(200, $status);
        self::assertStringContainsString('Řekni „ahoj“.', $telo, 'Český obsah s českou typografií.');
        self::assertStringContainsString('<div lang="cs">', $telo);
        self::assertStringContainsString(
            '<p class="stranka_jenCesky" lang="en"><em>' . htmlspecialchars('This page is only available in Czech for now.') . '</em></p>',
            $telo,
            'Poznámka je v jazyce stránky, i když je uvnitř českého obsahu.',
        );
        self::assertLessThan(strpos($telo, 'Řekni „ahoj“.'), strpos($telo, 'stranka_jenCesky'), 'Poznámka je nad obsahem.');
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/' . self::NEPRELOZENA . '">', $telo);
        self::assertStringNotContainsString('<link rel="alternate" hreflang', $telo);
    }

    /**
     * @test
     */
    public function neprelozenaCeskaStrankaJeBezPoznamky(): void
    {
        ['status' => $status, 'telo' => $telo] = $this->stahniVerejnouStranku('web/' . self::NEPRELOZENA);

        self::assertSame(200, $status);
        self::assertStringContainsString('Řekni „ahoj“.', $telo);
        self::assertStringNotContainsString('stranka_jenCesky', $telo);
        self::assertStringNotContainsString('<div lang=', $telo);
    }

    /**
     * @test
     */
    public function podstrankaLinieVedeZpetNaLiniiVJazyceStranky(): void
    {
        ['telo' => $anglicky] = $this->stahniVerejnouStranku('web/en/' . self::PODSTRANKA_LINIE);
        self::assertMatchesRegularExpression('~<a class="stranka_zpet" href="rpg">back to RPG</a>~', $anglicky);
        self::assertStringContainsString('<h1>Subpage</h1>', $anglicky);

        ['telo' => $cesky] = $this->stahniVerejnouStranku('web/' . self::PODSTRANKA_LINIE);
        self::assertMatchesRegularExpression('~<a class="stranka_zpet" href="rpg">zpět na RPG</a>~', $cesky);
    }

    /**
     * Anglický obsah má v editoru stránky vlastní pole s popiskem a ukládá se s ostatními.
     *
     * @test
     */
    public function editorStrankyMaPoleProAnglickyObsah(): void
    {
        $url = 'admin/web/editace-stranek?id=' . self::ID_UKLADANE;
        $telo = $this->fetchAdminPage($url);

        self::assertStringContainsString('<p class="dbffMdPopisek"><strong>Obsah anglicky (prázdné = stránka je jen česky)</strong></p>', $telo);
        self::assertMatchesRegularExpression('~<textarea[^>]*name="[^"]*\[obsah_en\][^"]*"~', $telo);
        self::assertSame(1, substr_count($telo, 'dbffMdPopisek"'), 'Český obsah popisek nemá, zůstává hlavním textem formuláře.');

        $pole = self::poleFormulare($telo);
        $klicAnglickehoObsahu = (string) array_key_first(array_filter(
            $pole,
            static fn (string $nazev): bool => str_contains($nazev, '[obsah_en]'),
            ARRAY_FILTER_USE_KEY,
        ));
        self::assertNotSame('', $klicAnglickehoObsahu);
        $pole[$klicAnglickehoObsahu] = "# Saved page\n\nIn English.";
        $this->odesliFormularAdminu($url, $pole);

        self::assertSame(
            "# Saved page\n\nIn English.",
            dbOneCol('SELECT obsah_en FROM stranky WHERE id_stranky = $0', [self::ID_UKLADANE]),
        );
        ['telo' => $anglicky] = $this->stahniVerejnouStranku('web/en/ukladana-testovaci-stranka');
        self::assertStringContainsString('<h1>Saved page</h1>', $anglicky);

        // prázdné pole = stránka je zase jen česky
        $pole[$klicAnglickehoObsahu] = '';
        $this->odesliFormularAdminu($url, $pole);
        ['telo' => $anglicky] = $this->stahniVerejnouStranku('web/en/ukladana-testovaci-stranka');
        self::assertStringContainsString('<h1>Ukládaná stránka</h1>', $anglicky);
        self::assertStringContainsString('stranka_jenCesky', $anglicky);
    }

    /**
     * Pole formuláře DbForm tak, jak by je odeslal prohlížeč (nezaškrtnutý checkbox chybí).
     *
     * @return array<string, string>
     */
    private static function poleFormulare(string $html): array
    {
        $dokument = new \DOMDocument();
        @$dokument->loadHTML('<?xml encoding="UTF-8">' . $html);
        $formular = (new \DOMXPath($dokument))->query('//form[contains(@class, "dbForm")]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $formular, 'Formulář editoru stránky');
        $pole = [];
        foreach ((new \DOMXPath($dokument))->query('.//input[@name] | .//textarea[@name] | .//select[@name]', $formular) as $prvek) {
            assert($prvek instanceof \DOMElement);
            $nazev = $prvek->getAttribute('name');
            if ($nazev === '') {
                continue;
            }
            $pole[$nazev] = match ($prvek->tagName) {
                'textarea' => $prvek->textContent,
                'select'   => (string) (new \DOMXPath($dokument))->evaluate('string(.//option[@selected]/@value)', $prvek),
                default    => $prvek->getAttribute('type') === 'checkbox' && ! $prvek->hasAttribute('checked')
                    ? null
                    : $prvek->getAttribute('value'),
            };
            if ($pole[$nazev] === null) {
                unset($pole[$nazev]);
            }
        }

        return $pole;
    }

    /**
     * Odešle formulář jako jeho skript v adminu: AJAXem, odpověď je JSON s případnou chybou.
     *
     * @param array<string, string> $pole
     */
    private function odesliFormularAdminu(string $url, array $pole): void
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'cookie');
        try {
            $this->loginToAdmin($cookieFile, \Uzivatel::SYSTEM_LOGIN, UNIVERZALNI_HESLO);
            $curl = curl_init('http://localhost/' . $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieFile);
            $this->setLocalServerCookieWithGameconTestDb($curl);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($pole));
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['X-Requested-With: XMLHttpRequest', 'Referer: http://localhost/' . $url]);
            $odpoved = (string) curl_exec($curl);

            self::assertSame(200, curl_getinfo($curl, CURLINFO_HTTP_CODE), $odpoved);
            self::assertStringNotContainsString('"error"', $odpoved, $odpoved);
        } finally {
            unlink($cookieFile);
        }
    }
}
