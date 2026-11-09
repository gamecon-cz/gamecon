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
