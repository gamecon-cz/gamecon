<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use App\Command\PrevestObrazkyNaAvifCommand;
use Gamecon\Prostredi\Prostredi;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\ZpracovaneObrazky;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class PrevestObrazkyNaAvifCommandTest extends AbstractTestDb
{
    use TestovaciObrazky;
    use UklidNovychAdresaru;

    protected static bool $disableStrictTransTables = true;

    private const int ID = 9921;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->uklidNoveAdresare($this->adresar());
        (new Filesystem())->mkdir($this->adresar());
        file_put_contents($this->adresar() . '/foto.jpg', self::fotkaZFotakuBajty(1200, 800));
        dbQuery(
            'INSERT INTO stranky(id_stranky, url_stranky, obsah, poradi) VALUES ($0, $1, $2, 0)',
            [self::ID, 'test-prevod-avif-prikaz', '![](' . $this->odkaz() . ')'],
        );
        dbQuery(
            'INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES ($0, $1, $2, 1, $3, 2, $4, 0, 5, 0, 0)',
            [self::ID, 'Testovací aktivita', 'testovaci-aktivita-prevod-avif-prikaz', ROCNIK, '![](' . $this->odkaz() . ')'],
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->kUklidu);
        $this->kUklidu = [];
        parent::tearDown();
    }

    /**
     * @test
     */
    public function bezPotvrzeniZalohyNicNezapise(): void
    {
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        $kod = $tester->execute([
            '--provest' => true,
            '--slozka'  => $this->slozka(),
        ]);

        self::assertSame(Command::FAILURE, $kod);
        self::assertStringContainsString('--zaloha-hotova', $tester->getDisplay());
        self::assertFileDoesNotExist($this->adresar() . '/foto.avif');
        self::assertSame('![](' . $this->odkaz() . ')', $this->textStranky());
    }

    /**
     * @test
     */
    public function suchyBehJenVypise(): void
    {
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        $kod = $tester->execute([
            '--slozka' => $this->slozka(),
        ], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);

        self::assertSame(Command::SUCCESS, $kod);
        $vystup = $tester->getDisplay();
        self::assertStringContainsString('Suchý běh', $vystup);
        self::assertStringContainsString($this->slozka() . '/foto.jpg: převedeno do AVIF', $vystup);
        self::assertFileDoesNotExist($this->adresar() . '/foto.avif');
        self::assertSame('![](' . $this->odkaz() . ')', $this->textStranky());
    }

    /**
     * @test
     */
    public function seZalohouPrevedeObrazekAPrepiseOdkazy(): void
    {
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        $kod = $tester->execute($this->prepinaceOstrehoBehu());

        self::assertSame(Command::SUCCESS, $kod);
        self::assertFileExists($this->adresar() . '/foto.avif');
        self::assertFileExists($this->adresar() . '/foto.jpg');
        self::assertSame('![](' . str_replace('foto.jpg', 'foto.avif', $this->odkaz()) . ')', $this->textStranky());
        self::assertStringContainsString('přepsané', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function nezapisujeKdyzSeProstrediNeshodujeSTimCoPoznal(): void
    {
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());
        $jineProstredi = Prostredi::detect() === Prostredi::Production
            ? Prostredi::Beta
            : Prostredi::Production;

        $kod = $tester->execute([
            ...$this->prepinaceOstrehoBehu(),
            '--prostredi' => $jineProstredi->value,
        ]);

        self::assertSame(Command::FAILURE, $kod);
        self::assertStringContainsString('hlásí k prostředí „' . Prostredi::detect()->value . '“', $tester->getDisplay());
        self::assertFileDoesNotExist($this->adresar() . '/foto.avif');
        self::assertSame('![](' . $this->odkaz() . ')', $this->textStranky());

        self::assertSame(Command::FAILURE, $tester->execute(array_diff_key($this->prepinaceOstrehoBehu(), [
            '--prostredi' => true,
        ])));
        self::assertFileDoesNotExist($this->adresar() . '/foto.avif');
    }

    /**
     * @test
     */
    public function nezapisujeKdyzProstrediNesediKAdreseriNeboNastaveni(): void
    {
        $vBetaAdresari = new class extends PrevestObrazkyNaAvifCommand {
            protected function getAdresarProjektu(): string
            {
                return '/srv/www/beta';
            }
        };
        $sNastavenimOstre = new class extends PrevestObrazkyNaAvifCommand {
            protected function getUrlWebu(): string
            {
                return 'https://gamecon.cz';
            }
        };

        foreach ([$vBetaAdresari, $sNastavenimOstre] as $prikaz) {
            $tester = new CommandTester($prikaz);

            self::assertSame(Command::FAILURE, $tester->execute($this->prepinaceOstrehoBehu()));
            self::assertStringContainsString('nic se nezapsalo', $tester->getDisplay());
            self::assertFileDoesNotExist($this->adresar() . '/foto.avif');
            self::assertSame('![](' . $this->odkaz() . ')', $this->textStranky());
        }
    }

    /**
     * @test
     */
    public function mazaniOriginaluSeZapneJinymPrepinacem(): void
    {
        (new CommandTester(new PrevestObrazkyNaAvifCommand()))->execute($this->prepinaceOstrehoBehu());
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        $suchy = $tester->execute([
            '--smazat-puvodni' => true,
            '--slozka'         => $this->slozka(),
        ]);
        self::assertSame(Command::SUCCESS, $suchy);
        self::assertFileExists($this->adresar() . '/foto.jpg');

        $kod = $tester->execute([
            ...$this->prepinaceOstrehoBehu(),
            '--smazat-puvodni' => true,
        ]);

        self::assertSame(Command::SUCCESS, $kod);
        self::assertFileDoesNotExist($this->adresar() . '/foto.jpg');
        self::assertFileExists($this->adresar() . '/foto.avif');
    }

    /**
     * @test
     */
    public function limitMusiBytKladneCislo(): void
    {
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        self::assertSame(Command::FAILURE, $tester->execute([
            '--limit' => '0',
        ]));
        self::assertSame(Command::FAILURE, $tester->execute([
            '--limit' => 'mnoho',
        ]));
    }

    /**
     * @test
     */
    public function limitNejdeSMazanimOriginalu(): void
    {
        (new CommandTester(new PrevestObrazkyNaAvifCommand()))->execute($this->prepinaceOstrehoBehu());
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        $kod = $tester->execute([
            ...$this->prepinaceOstrehoBehu(),
            '--smazat-puvodni' => true,
            '--limit'          => '1',
        ]);

        self::assertSame(Command::FAILURE, $kod);
        self::assertStringContainsString('--limit jde jen s převodem', $tester->getDisplay());
        self::assertFileExists($this->adresar() . '/foto.jpg');
    }

    /**
     * @test
     */
    public function oblastMusiBytJednaZNabidky(): void
    {
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        self::assertSame(Command::FAILURE, $tester->execute([
            '--oblast' => 'texty',
        ]));
        self::assertStringContainsString('--oblast chce obsah, fotky nebo vse', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function slozkaNejdeSOblastiFotek(): void
    {
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        self::assertSame(Command::FAILURE, $tester->execute([
            '--oblast' => 'fotky',
            '--slozka' => $this->slozka(),
        ]));
        self::assertStringContainsString('--slozka omezuje obrázky v textech webu', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function sOmezenimNaSlozkuSeFotkyUzivateluNepreviMaji(): void
    {
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        $kod = $tester->execute([
            '--slozka' => $this->slozka(),
        ]);

        self::assertSame(Command::SUCCESS, $kod);
        self::assertStringContainsString('Obrázky v textech webu', $tester->getDisplay());
        self::assertStringNotContainsString('Fotky uživatelů', $tester->getDisplay());
        self::assertStringContainsString('nepřevádí se', $tester->getDisplay());
    }

    /**
     * Fotka uživatele se v suchém běhu ukáže ve svém oddílu a nic se nezapíše.
     *
     * @test
     */
    public function suchyBehUkazeFotkyUzivatelu(): void
    {
        $slozkaFotek = dirname($this->ulozFotkuUzivatele('99436.jpg', self::fotkaZFotakuBajty(1200, 800)));
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        $tester->execute([
            '--oblast' => 'fotky',
        ], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);

        $vystup = $tester->getDisplay();
        self::assertStringContainsString('Fotky uživatelů', $vystup);
        self::assertStringNotContainsString('Obrázky v textech webu', $vystup);
        self::assertStringContainsString('99436.jpg: převedeno do AVIF', $vystup);
        self::assertFileDoesNotExist($slozkaFotek . '/99436.avif');
    }

    /**
     * @test
     */
    public function vycerpanyLimitNaTextechRekneZeFotkySeNeprevadely(): void
    {
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        $tester->execute([
            '--limit' => '1',
        ]);

        self::assertStringContainsString('Fotky uživatelů se v tomhle běhu nepřevádějí: limit se vyčerpal', (string) preg_replace('~[\s\n]+~', ' ', $tester->getDisplay()));
    }

    /**
     * Kopii chce příkaz jen těch adresářů, do kterých se zapisuje: fotky uživatelů nejsou v databázi, texty ano.
     *
     * @test
     */
    public function zalohuChceJenProToCoSePrevadi(): void
    {
        $chyba = function (array $prepinace): string {
            $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());
            self::assertSame(Command::FAILURE, $tester->execute([
                '--provest' => true,
                ...$prepinace,
            ]));

            return (string) preg_replace('~\s+~', ' ', $tester->getDisplay());
        };

        $jenFotky = $chyba([
            '--oblast' => 'fotky',
        ]);
        self::assertStringContainsString('Udělej kopii web/soubory/systemove/fotky a spusť', $jenFotky);
        self::assertStringNotContainsString('web/soubory/obsah', $jenFotky);
        self::assertStringNotContainsString('databáze', $jenFotky);

        $jenTexty = $chyba([
            '--oblast' => 'obsah',
        ]);
        self::assertStringContainsString('Udělej kopii web/soubory/obsah a export databáze a spusť', $jenTexty);
        self::assertStringNotContainsString('fotky', $jenTexty);

        self::assertStringContainsString(
            'Udělej kopii web/soubory/obsah a web/soubory/systemove/fotky a export databáze a spusť',
            $chyba([]),
        );
    }

    /**
     * Příkaz se pouští ze skriptu a obrázek, který se nepodařilo převést, nesmí skončit jako povedený běh.
     *
     * @test
     */
    public function selhaniJeVidetVNavratovemKoduAVypisu(): void
    {
        // symlink bez cíle: AVIF vedle zdroje nejde uložit a nejde ani přepsat
        symlink($this->adresar() . '/neexistuje.avif', $this->adresar() . '/foto.avif');
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        $kod = $tester->execute($this->prepinaceOstrehoBehu());

        self::assertSame(Command::FAILURE, $kod);
        self::assertStringContainsString('U 1 obrázků se práce nezdařila', (string) preg_replace('~\s+~', ' ', $tester->getDisplay()));
        self::assertSame('![](' . $this->odkaz() . ')', $this->textStranky());
    }

    /**
     * Suchý běh stačí: zakóduje fotku stejně jako ostrý a do skutečné složky fotek nic nezapíše.
     *
     * @test
     */
    public function selhaniFotkyJeVidetVNavratovemKoduAVypisu(): void
    {
        $this->ulozFotkuUzivatele('99435.jpg', self::jpgKterySeNedaDekodovat());
        $tester = new CommandTester(new PrevestObrazkyNaAvifCommand());

        $kod = $tester->execute([
            '--oblast' => 'fotky',
        ]);

        self::assertSame(Command::FAILURE, $kod);
        self::assertStringContainsString('se práce nezdařila', (string) preg_replace('~\s+~', ' ', $tester->getDisplay()));
    }

    /**
     * @return string absolutní cesta uložené fotky ve skutečné složce fotek
     */
    private function ulozFotkuUzivatele(string $nazev, string $bajty): string
    {
        $slozkaFotek = ZpracovaneObrazky::adresarFotekUzivatelu();
        $this->uklidNoveAdresare($slozkaFotek);
        (new Filesystem())->mkdir($slozkaFotek);
        $fotka = $slozkaFotek . '/' . $nazev;
        $this->kUklidu[] = $fotka;
        file_put_contents($fotka, $bajty);

        return $fotka;
    }

    /**
     * @return array<string, bool|string>
     */
    private function prepinaceOstrehoBehu(): array
    {
        return [
            '--provest'       => true,
            '--zaloha-hotova' => true,
            '--prostredi'     => Prostredi::detect()->value,
            '--slozka'        => $this->slozka(),
        ];
    }

    private function slozka(): string
    {
        return 'test-prevod-avif-prikaz-' . getmypid();
    }

    private function adresar(): string
    {
        return ZpracovaneObrazky::adresarObsahu() . '/' . $this->slozka();
    }

    private function odkaz(): string
    {
        return 'soubory/obsah/' . $this->slozka() . '/foto.jpg';
    }

    private function textStranky(): string
    {
        return (string) dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID]);
    }
}
