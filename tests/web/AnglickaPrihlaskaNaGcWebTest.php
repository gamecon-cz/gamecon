<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Role\Role;

/**
 * Přihláška na GameCon (`/prihlaska`) pod /en/ přes skutečný Apache ve všech stavech stránky: před spuštěním
 * přihlašování, formulář, uzavřené přihlašování, během GameConu a po něm. Rámec stránky je anglicky, e-shop
 * (předměty, ubytování, jídlo, vstupné) zůstává česky v `lang="cs"`; česká verze beze změny.
 *
 * Termíny se nastavují v systémovém nastavení kolem skutečného teď, Apache čas podstrčit nejde.
 */
class AnglickaPrihlaskaNaGcWebTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const int ID_PRITOMNEHO = 9861;
    private const int ID_PRIHLASENEHO_MLADSIHO_15 = 9862;
    private const int ID_NEPRIHLASENEHO = 9863;
    private const int ID_ODESILAJICIHO = 9864;
    private const int ID_ORGANIZATORKY = 9865;

    private const string LOGIN_PRITOMNEHO = 'test-prihlaska-pritomny';
    private const string LOGIN_PRIHLASENEHO_MLADSIHO_15 = 'test-prihlaska-mladsi';
    private const string LOGIN_NEPRIHLASENEHO = 'test-prihlaska-novy';
    private const string LOGIN_ODESILAJICIHO = 'test-prihlaska-odesila';
    private const string LOGIN_ORGANIZATORKY = 'test-prihlaska-organizatorka';

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $mladsi15 = (date('Y') - 10) . '-01-01';

        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            [
                <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele, pohlavi, datum_narozeni)
VALUES
    ($0, $1, 'Test', 'Přítomný', 'test-prihlaska-pritomny@example.invalid', 'm', '1990-01-01'),
    ($2, $3, 'Test', 'Mladší', 'test-prihlaska-mladsi@example.invalid', 'f', $8),
    ($4, $5, 'Test', 'Nový', 'test-prihlaska-novy@example.invalid', 'f', '1990-01-01'),
    ($6, $7, 'Test', 'Odesílající', 'test-prihlaska-odesila@example.invalid', 'm', '1990-01-01'),
    ($9, $10, 'Test', 'Organizátorka', 'test-prihlaska-organizatorka@example.invalid', 'f', '1990-01-01')
SQL,
                [
                    0  => self::ID_PRITOMNEHO,
                    1  => self::LOGIN_PRITOMNEHO,
                    2  => self::ID_PRIHLASENEHO_MLADSIHO_15,
                    3  => self::LOGIN_PRIHLASENEHO_MLADSIHO_15,
                    4  => self::ID_NEPRIHLASENEHO,
                    5  => self::LOGIN_NEPRIHLASENEHO,
                    6  => self::ID_ODESILAJICIHO,
                    7  => self::LOGIN_ODESILAJICIHO,
                    8  => $mladsi15,
                    9  => self::ID_ORGANIZATORKY,
                    10 => self::LOGIN_ORGANIZATORKY,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0), ($0, $2, $0), ($3, $1, $3), ($4, 1, $4), ($4, $5, $4)
SQL,
                [
                    0 => self::ID_PRITOMNEHO,
                    1 => Role::PRIHLASEN_NA_LETOSNI_GC,
                    2 => Role::PRITOMEN_NA_LETOSNIM_GC,
                    3 => self::ID_PRIHLASENEHO_MLADSIHO_15,
                    // všemocná role (všechna práva, tedy i slevy a ubytování zdarma) z AbstractTestWeb a organizátorka
                    4 => self::ID_ORGANIZATORKY,
                    5 => Role::ORGANIZATOR,
                ],
            ],
        ];
    }

    public function testPoGameconuDekujeZaUcastAOdkazNaZpetnouVazbuZustavaVJazyce(): void
    {
        $this->nastavTerminy(gcOdDni: -10, gcDoDni: -7, regOdDni: -60, regDoDni: -7);
        $session = $this->prihlas(self::LOGIN_PRITOMNEHO);

        $anglicky = $this->stahniVerejnou($session, $this->cesta('en/prihlaska'));
        $this->assertPrelozenaStranka($anglicky);
        $text = $this->textObsahu($anglicky);
        self::assertStringContainsString('GameCon registration', $text);
        self::assertStringContainsString('Thank you for coming to GameCon! We’d love to hear any feedback you have', $text);
        self::assertStringContainsString('you can follow us on Facebook, Instagram or Discord.', $text);
        self::assertStringContainsString('<a href="info-po-gc" title="Feedback">feedback</a>', $anglicky, 'Odkaz na zpětnou vazbu je relativní, zůstane v jazyce stránky.');
        $this->assertBezCeskehoTextu($text);

        $cesky = $this->stahniVerejnou($session, $this->cesta('prihlaska'));
        self::assertStringContainsString(
            'Přihláška na GameCon Děkujeme za tvou účast na GameConu! Budeme moc rádi za jakoukoliv zpětnou vazbu, abychom věděli, co zlepšit, případně co určitě nevynechat ani v příštích letech. Budeme se těšit zase za rok, do té doby nás můžeš sledovat na Facebooku, Instagramu nebo na Discordu.',
            $this->textObsahu($cesky),
        );
        self::assertStringContainsString('<a href="info-po-gc" title="Zpětná vazba">zpětnou vazbu</a>', $cesky);

        $zpetnaVazba = $this->otevriAdresu($session, URL_WEBU . '/en/info-po-gc');
        self::assertSame(301, $zpetnaVazba['kod']);
        self::assertSame(URL_WEBU . '/en/prakticke-informace#dotazniky', $zpetnaVazba['kam'], 'Zpětná vazba vede na anglické praktické informace.');
        self::assertSame(URL_WEBU . '/prakticke-informace#dotazniky', $this->otevriAdresu($session, URL_WEBU . '/info-po-gc')['kam']);
    }

    public function testPoGameconuBezUcastiZveNaPristiRocnik(): void
    {
        $this->nastavTerminy(gcOdDni: -10, gcDoDni: -7, regOdDni: -60, regDoDni: -7);
        $pristiRok = (string) (ROCNIK + 1);

        $anglicky = $this->stahniVerejnouStranku($this->cesta('en/prihlaska'));
        self::assertSame(200, $anglicky['status']);
        $this->assertPrelozenaStranka($anglicky['telo']);
        self::assertStringContainsString('hreflang="cs" href="' . URL_WEBU . '/prihlaska"', $anglicky['telo']);
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/en/prihlaska">', $anglicky['telo']);
        $text = $this->textObsahu($anglicky['telo']);
        self::assertStringContainsString("GameCon is over, we look forward to seeing you in {$pristiRok}.", $text);
        self::assertStringContainsString('You’ll find photos from previous years on our Facebook or Instagram. Join us on Discord so you don’t miss any news.', $text);
        $this->assertBezCeskehoTextu($text);

        $cesky = $this->stahniVerejnouStranku($this->cesta('prihlaska'));
        self::assertStringContainsString('<html lang="cs">', $cesky['telo']);
        self::assertStringContainsString(
            "Přihláška na GameCon GameCon už proběhl, těšíme se na viděnou v roce {$pristiRok}. Fotky z předešlých ročníků najdeš na našem Facebooku nebo Instagramu. Přidej se k nám na Discord, aby ti neutekly žádné novinky.",
            $this->textObsahu($cesky['telo']),
        );
    }

    public function testPrihlasovaniUzavreno(): void
    {
        $this->nastavTerminy(gcOdDni: 10, gcDoDni: 13, regOdDni: -60, regDoDni: -1);
        $konec = $this->termin('REG_GC_DO');

        $anglicky = $this->stahniVerejnouStranku($this->cesta('en/prihlaska'));
        $this->assertPrelozenaStranka($anglicky['telo']);
        $text = $this->textObsahu($anglicky['telo']);
        self::assertStringContainsString('GameCon registration closed on ' . $konec->format('j F Y \a\t H:i') . '.', $text);
        $this->assertBezCeskehoTextu($text);

        self::assertStringContainsString(
            'Přihláška na GameCon Přihlašování na GameCon je uzavřeno od ' . $konec->format('j. n. Y H:i:s') . '.',
            $this->textObsahu($this->stahniVerejnouStranku($this->cesta('prihlaska'))['telo']),
        );
    }

    public function testBehemGameconuJdouZmenyJenNaInfopultu(): void
    {
        $this->nastavTerminy(gcOdDni: -1, gcDoDni: 2, regOdDni: -60, regDoDni: 2);

        $pritomny = $this->prihlas(self::LOGIN_PRITOMNEHO);
        $anglicky = $this->stahniVerejnou($pritomny, $this->cesta('en/prihlaska'));
        $this->assertPrelozenaStranka($anglicky);
        $text = $this->textObsahu($anglicky);
        self::assertStringContainsString('Your registration can no longer be changed online; any changes can currently only be made in person at the info desk.', $text);
        self::assertStringContainsString('You’ll find an overview of your orders and payments under Payments in your profile.', $text);
        self::assertStringContainsString('<a href="finance">Payments</a>', $anglicky);
        $this->assertBezCeskehoTextu($text);
        self::assertStringContainsString(
            'Přihlášku již nelze upravovat online, případné změny je momentálně možné provádět pouze na místě u Infopultu. Přehled svých objednávek a plateb najdeš v záložce finance na svém profilu.',
            $this->textObsahu($this->stahniVerejnou($pritomny, $this->cesta('prihlaska'))),
        );

        $prihlaseny = $this->prihlas(self::LOGIN_PRIHLASENEHO_MLADSIHO_15);
        $text = $this->textObsahu($this->stahniVerejnou($prihlaseny, $this->cesta('en/prihlaska')));
        self::assertStringContainsString('Welcome to GameCon! Your registration can no longer be changed online, so please sort out any changes at the info desk. You’ll find an overview of your payments under Payments in your profile.', $text);
        $this->assertBezCeskehoTextu($text);
        self::assertStringContainsString(
            'Vítáme tě na GameConu! Přihlášku již nelze upravovat online, veškeré případné změny v přihlášce proto řeš na Infopultu, přehled plateb najdeš v záložce finance na svém profilu.',
            $this->textObsahu($this->stahniVerejnou($prihlaseny, $this->cesta('prihlaska'))),
        );
    }

    public function testPredSpustenimPrihlasovaniUkazeKdyZacne(): void
    {
        $this->nastavTerminy(gcOdDni: 60, gcDoDni: 63, regOdDni: 10, regDoDni: 63);
        $zacatek = $this->termin('REG_GC_OD');
        $session = $this->prihlas(self::LOGIN_NEPRIHLASENEHO);

        $anglicky = $this->stahniVerejnou($session, $this->cesta('en/prihlaska'));
        $this->assertPrelozenaStranka($anglicky);
        $text = $this->textObsahu($anglicky);
        self::assertStringContainsString('GameCon registration opens on ' . $zacatek->format('j F Y \a\t H:i') . '.', $text);
        $this->assertBezCeskehoTextu($text);

        self::assertStringContainsString(
            'Přihláška na GameCon Přihlašování na GameCon bude spuštěno ' . $zacatek->format('j. n. Y \v H:i') . '.',
            $this->textObsahu($this->stahniVerejnou($session, $this->cesta('prihlaska'))),
        );
    }

    public function testNeprihlasenehoPosleNaPrihlaseniSAnglickouHlaskou(): void
    {
        $this->nastavTerminy(gcOdDni: 30, gcDoDni: 33, regOdDni: -10, regDoDni: 33);

        $anglicky = $this->getNovaRelace();
        $presmerovani = $this->otevriAdresu($anglicky, URL_WEBU . '/en/prihlaska');
        self::assertContains($presmerovani['kod'], [302, 303]);
        self::assertSame(URL_WEBU . '/en/prihlaseni', $presmerovani['kam']);
        self::assertStringContainsString('You need to be logged in to view this page.', $this->stahniVerejnou($anglicky, $this->cesta('en/prihlaseni')));

        $odeslani = $this->getNovaRelace();
        $curl = $this->curlNaAdresu($odeslani, URL_WEBU . '/en/prihlaska');
        curl_setopt($curl, CURLOPT_POSTFIELDS, [
            'prihlasitNeboUpravit' => '1',
        ]);
        // ochrana proti CSRF (omezCsrf()) chce odkazujícího ze stejného webu
        curl_setopt($curl, CURLOPT_REFERER, URL_WEBU . '/en/prihlaska');
        $kam = $this->proved($curl)['kam'];
        unset($curl); // až teď curl zapíše cookies (s hláškou) do souboru
        self::assertSame(URL_WEBU . '/en/prihlaseni', $kam);
        self::assertStringContainsString(
            'You were logged out in the meantime, so your registration was not saved. Please log in again and submit it once more.',
            $this->stahniVerejnou($odeslani, $this->cesta('en/prihlaseni')),
        );

        $cesky = $this->getNovaRelace();
        self::assertSame(URL_WEBU . '/prihlaseni', $this->otevriAdresu($cesky, URL_WEBU . '/prihlaska')['kam']);
        self::assertStringContainsString('Tato stránka vyžaduje přihlášení', $this->stahniVerejnou($cesky, $this->cesta('prihlaseni')));
    }

    public function testFormularMaAnglickyRamecAEshopCesky(): void
    {
        $this->nastavTerminy(gcOdDni: 30, gcDoDni: 33, regOdDni: -10, regDoDni: 33);
        $session = $this->prihlas(self::LOGIN_NEPRIHLASENEHO);

        $anglicky = $this->stahniVerejnou($session, $this->cesta('en/prihlaska'));
        $this->assertPrelozenaStranka($anglicky);
        $text = $this->textBezCestiny($anglicky);
        foreach ([
            'GameCon registration',
            'Fill in the form to register for GameCon ' . ROCNIK . '.',
            'Merchandise',
            'GameCon merch',
            'You’ll find the hoodie sizes here.',
            'Accommodation',
            'About accommodation',
            'The numbers show the places taken / total capacity.',
            'Accommodation is available from Wednesday, the programme starts on Thursday at noon.',
            'Accommodation can only be booked for at least 2 consecutive nights.',
            'You’ll find more about accommodation and room types on the Practical information page.',
            'Food',
            'About meals',
            'The menu is available here.',
            'Thank you!',
            'Voluntary admission',
            'Would you like to help organise GameCon?',
            'Volunteers',
            'Game Masters',
            'Here you can tell us more about how you’d like to get involved.',
        ] as $anglickyText) {
            self::assertStringContainsString($anglickyText, $text);
        }
        self::assertStringContainsString('value="Register for GameCon"', $anglicky);
        self::assertStringContainsString('<a href="prakticke-informace#ubytovani">Practical information</a>', $anglicky);
        self::assertStringContainsString('<a href="program">programme</a>', $anglicky);
        $this->assertBezCeskehoTextu($text);
        // e-shop zůstává česky, s jazykem označeným pro čtečky
        $vCestine = $this->textVCestine($anglicky);
        self::assertStringContainsString('ČÁSTKA:', $vCestine, 'Dobrovolné vstupné je česky v lang="cs".');
        self::assertStringContainsString('Na pokoji chci být s:', $vCestine, 'Ubytování je česky v lang="cs".');

        $cesky = $this->stahniVerejnou($session, $this->cesta('prihlaska'));
        $text = $this->textObsahu($cesky);
        foreach ([
            'Přihláška na GameCon Vyplněním údajů se přihlásíš na GameCon ' . ROCNIK . '. Můžeš si vybrat předměty s GameCon tématikou a ubytování, které budeš chtít. Výběr jde i později změnit.',
            'Předměty GameCon merch Placky, kostky, mikiny i trička s logem GameConu si můžeš objednat ve více kusech. Stačí kliknout na tlačítko ➕. U triček a mikin se po vyplnění jednoho automaticky zobrazí možnost přidat další. Velikosti mikin najdeš zde.',
            'Ubytování Informace k ubytování Objednat nebo změnit ubytování můžeš do ',
            'Čísla ukazují počet obsazených míst / celkovou kapacitu. Ubytování je k dispozici od středy, program začíná ve čtvrtek v poledne. Ubytování je možné objednat minimálně na 2 navazující noci. Hotelové pokoje mají v ceně zahrnutou snídani. Podrobnější informace o ubytování a typech pokojů najdeš na stránce Praktické informace.',
            'Jídlo Informace ke stravování Jídlo je možné objednat nebo zrušit do ',
            'Na výběr je z 3–5 jídel, která jsou vždy stejná na oběd i večeři a jedno vegetariánské jídlo. Jídelníček je k dispozici zde.',
            'Děkujeme! Příspěvků formou dobrovolného vstupného si moc vážíme a děkujeme za ně. I díky nim dokážeme GameCon každý rok dělat o něco lepší! Dobrovolné vstupné ČÁSTKA:',
            'Chceš se zapojit do organizace? Pokud chceš jako dobrovolník pomoct s přípravou na místě, přihlaš se v programu na některou z dobrovolnických aktivit (Příprava, Úklid).',
            'Dobrovolníci DobrovolníciZa to, že je GameCon takový jaký je, vděčíme nejen vypravěčům a organizátorům',
            'Zde můžeš napsat více o tom, jak se chceš zapojit. Hlas se prosím o výpomoc jen u věcí, které zatím nemáš s nikým domluvené:',
        ] as $ceskyText) {
            self::assertStringContainsString($ceskyText, $text);
        }
        self::assertStringContainsString('value="Přihlásit na GameCon"', $cesky);
        self::assertStringNotContainsString('lang="cs"', $this->obsah($cesky), 'Česká stránka jazyk e-shopu neoznačuje, je v jazyce stránky.');
    }

    public function testFormularPrihlasenehoMladsihoPatnactiLet(): void
    {
        $this->nastavTerminy(gcOdDni: 30, gcDoDni: 33, regOdDni: -10, regDoDni: 33);
        $session = $this->prihlas(self::LOGIN_PRIHLASENEHO_MLADSIHO_15);

        $anglicky = $this->stahniVerejnou($session, $this->cesta('en/prihlaska'));
        $text = $this->textBezCestiny($anglicky);
        self::assertStringContainsString('Your GameCon ' . ROCNIK . ' registration is active!', $text);
        self::assertMatchesRegularExpression('~Your order must be paid by \d{1,2} [A-Z][a-z]+ at the latest\. You’ll find your financial overview and payment details under Payments\.~', $text);
        self::assertStringContainsString('As you are under 15, you need the consent of your parent or legal guardian to take part in GameCon.', $text);
        self::assertStringContainsString('form (in Czech)', $text);
        self::assertStringContainsString('/soubory/obsah/materialy/' . ROCNIK . '/souhlas_rodicu_' . ROCNIK . '.pdf', $anglicky);
        self::assertStringContainsString('value="Upload"', $anglicky);
        self::assertStringContainsString('value="Save changes"', $anglicky);
        self::assertStringContainsString('value="Cancel my GameCon registration"', $anglicky);
        self::assertStringContainsString('return confirm(&quot;Cancelling your GameCon registration also cancels', $anglicky);
        $this->assertBezCeskehoTextu($text);

        $cesky = $this->stahniVerejnou($session, $this->cesta('prihlaska'));
        $text = $this->textObsahu($cesky);
        self::assertStringContainsString('Tvá přihláška na GameCon ' . ROCNIK . ' je aktivní! Nyní můžeš upravovat objednané ubytování i předměty až téměř do začátku GameConu.', $text);
        self::assertMatchesRegularExpression('~Objednávku je potřeba uhradit do \d{1,2}\. \d{1,2}\. včetně\. Svůj přehled financí a údaje k platbě najdeš v Přehledu financí\.~', $text);
        self::assertStringContainsString('Jakožto mladší 15ti let budeš potřebovat dodat potvrzení o souhlasu tvého zákonného zástupce s účastí na GameConu. Formulář ke stažení můžeš najít na tomto odkazu a vyplněný nám jej můžeš kdykoliv před příjezdem na GameCon nahrát pomocí tohoto formuláře, nebo donést fyzicky při příjezdu na GameCon k Infopultu. Připravené formuláře k vyplnění budeme mít i na Infopultu.', $text);
        self::assertStringContainsString('value="Uložit změny"', $cesky);
        self::assertStringContainsString('value="Odhlásit se z GameConu"', $cesky);
        self::assertStringContainsString('Odhlášení z GameConu zruší všechny tvé registrace na aktivity a nákupy předmětů. Kliknutím na OK se odhlásíš.', $cesky);
    }

    public function testFormularOrganizatorkySeSlevamiAUbytovanimZdarma(): void
    {
        $this->nastavTerminy(gcOdDni: 30, gcDoDni: 33, regOdDni: -10, regDoDni: 33);
        $session = $this->prihlas(self::LOGIN_ORGANIZATORKY);

        $anglicky = $this->stahniVerejnou($session, $this->cesta('en/prihlaska'));
        $text = $this->textBezCestiny($anglicky);
        self::assertMatchesRegularExpression('~Your role \(\) entitles you to the following bonuses \(if you order them\): [^.]*free accommodation~', $text, 'Název role je česky v lang="cs", text bez češtiny ho nemá.');
        self::assertStringNotContainsString('free meals', $text, 'Slevy na jídlo ukazuje tabulka jídla, v rámečku nejsou.');
        self::assertStringNotContainsString('discounted meals', $text);
        self::assertStringContainsString('As an organiser, you get all your accommodation for free.', $text);
        self::assertStringContainsString('The “Ne–Po” option is the night from Sunday to Monday, when the programme is already over.', $text);
        self::assertStringNotContainsString('Accommodation can only be booked for at least 2 consecutive nights.', $text);
        self::assertMatchesRegularExpression('~Your role \(<span lang="cs">[^<]*organizátorka[^<]*</span>\)~u', $anglicky);
        $this->assertBezCeskehoTextu($text);

        $text = $this->textObsahu($this->stahniVerejnou($session, $this->cesta('prihlaska')));
        self::assertMatchesRegularExpression('~Jako [^:]*organizátorka[^:]* máš nárok na následující bonusy \(pokud si je objednáš\): [^.]*ubytování zdarma~u', $text);
        self::assertStringNotContainsString('jídlo zdarma', $text);
        self::assertStringContainsString('Jako organizátorka máš veškeré ubytování zdarma.', $text);
        self::assertStringContainsString('Varianta „Ne–Po“ znamená noc z neděle na pondělí, kdy už neprobíhá program. V pondělí je potřeba pokoj vyklidit do 9:00.', $text);
    }

    public function testPoOdeslaniPotvrdiPrihlaseniAnglicky(): void
    {
        $this->nastavTerminy(gcOdDni: 30, gcDoDni: 33, regOdDni: -10, regDoDni: 33);
        $session = $this->prihlas(self::LOGIN_ODESILAJICIHO);

        $curl = $this->curlNaAdresu($session, URL_WEBU . '/en/prihlaska');
        curl_setopt($curl, CURLOPT_POSTFIELDS, [
            'prihlasitNeboUpravit' => '1',
        ]);
        curl_setopt($curl, CURLOPT_REFERER, URL_WEBU . '/en/prihlaska');
        $odeslani = $this->proved($curl);
        unset($curl); // až teď curl zapíše cookies (s hláškou) do souboru
        self::assertContains($odeslani['kod'], [302, 303], $odeslani['telo']);

        $stranka = $this->stahniVerejnou($session, $this->cesta('en/prihlaska'));
        self::assertStringContainsString('You are registered for GameCon ' . ROCNIK . '.', $stranka);
        self::assertStringContainsString('value="Save changes"', $stranka);
    }

    private function nastavTerminy(int $gcOdDni, int $gcDoDni, int $regOdDni, int $regDoDni): void
    {
        foreach ([
            'GC_BEZI_OD' => $gcOdDni,
            'GC_BEZI_DO' => $gcDoDni,
            'REG_GC_OD'  => $regOdDni,
            'REG_GC_DO'  => $regDoDni,
        ] as $klic => $dni) {
            dbQuery(<<<'SQL'
UPDATE systemove_nastaveni
SET vlastni = 1, hodnota = DATE_FORMAT(DATE_ADD(NOW(), INTERVAL $0 DAY), '%Y-%m-%d %H:%i:%s')
WHERE klic = $1
SQL,
                [
                    0 => $dni,
                    1 => $klic,
                ],
            );
        }
    }

    private function termin(string $klic): \DateTimeImmutable
    {
        return new \DateTimeImmutable((string) dbOneCol('SELECT hodnota FROM systemove_nastaveni WHERE klic = $0', [
            0 => $klic,
        ]));
    }

    /**
     * Cesta stránky webu od kořene serveru pro {@see stahniVerejnou()}, třeba `/web/en/prihlaska`.
     */
    private function cesta(string $stranka): string
    {
        return parse_url(URL_WEBU, PHP_URL_PATH) . '/' . $stranka;
    }

    private function assertPrelozenaStranka(string $telo): void
    {
        self::assertStringContainsString('<html lang="en">', $telo);
        self::assertDoesNotMatchRegularExpression('~<div lang="cs">\s*<div><div class="prihlaska_hlavickaObal">~', $telo, 'Přihláška je přeložená, rámec stránky není česky.');
        self::assertStringContainsString('<link rel="alternate" hreflang="en" href="' . URL_WEBU . '/en/prihlaska">', $telo);
        self::assertMatchesRegularExpression('~<title>[^<]*Registration</title>~', $telo);
    }

    /**
     * Obsah stránky od hlavičky přihlášky do konce stránky, bez lišty profileru.
     */
    private function obsah(string $telo): string
    {
        $zacatek = strpos($telo, '<div class="prihlaska_hlavickaObal">');
        self::assertNotFalse($zacatek, 'Na stránce chybí hlavička přihlášky.');
        $obsah = substr($telo, $zacatek);
        foreach (['<div class="corner-ribbon"', '<div class="profilInfo"', '</body>'] as $konec) {
            $pozice = strpos($obsah, $konec);
            if ($pozice !== false) {
                $obsah = substr($obsah, 0, $pozice);
            }
        }

        return $obsah;
    }

    /**
     * Text obsahu bez značek, skriptů, stylů a komentářů, mezery sloučené.
     */
    private function textObsahu(string $telo): string
    {
        $obsah = (string) preg_replace(['~<script\b.*?</script>~s', '~<style\b.*?</style>~s', '~<!--.*?-->~s'], '', $this->obsah($telo));

        return trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($obsah))));
    }

    /**
     * Text obsahu bez částí označených `lang="cs"` (e-shop), tedy to, co má být anglicky.
     */
    private function textBezCestiny(string $telo): string
    {
        $xpath = $this->xpathObsahu($telo);
        foreach (iterator_to_array($xpath->query('//*[@lang="cs"] | //script | //style')) as $uzel) {
            $uzel->parentNode?->removeChild($uzel);
        }

        return trim((string) preg_replace('~\s+~u', ' ', $xpath->document->textContent));
    }

    /**
     * Text částí obsahu označených `lang="cs"`.
     */
    private function textVCestine(string $telo): string
    {
        $xpath = $this->xpathObsahu($telo);
        $texty = [];
        foreach ($xpath->query('//*[@lang="cs"]') as $uzel) {
            $texty[] = $uzel->textContent;
        }

        return trim((string) preg_replace('~\s+~u', ' ', implode(' ', $texty)));
    }

    private function xpathObsahu(string $telo): \DOMXPath
    {
        $dokument = new \DOMDocument();
        $dokument->loadHTML(
            '<?xml encoding="utf-8"?><div>' . $this->obsah($telo) . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        return new \DOMXPath($dokument);
    }

    private function assertBezCeskehoTextu(string $text): void
    {
        self::assertDoesNotMatchRegularExpression(
            '~[ěščřžýáíéóúůťďňĚŠČŘŽÝÁÍÉÓÚŮŤĎŇ]~u',
            $text,
            'Na anglické stránce nezůstal český text.',
        );
    }
}
