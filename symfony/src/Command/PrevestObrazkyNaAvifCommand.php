<?php

declare(strict_types=1);

namespace App\Command;

use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura;
use Gamecon\Prostredi\Prostredi;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Web\ImagickAvif;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\OptimalizaceObrazku;
use Gamecon\Web\PolozkaPrevoduObrazku;
use Gamecon\Web\PouzitiObrazku;
use Gamecon\Web\PrevodObrazkuNaAvif;
use Gamecon\Web\StavPrevoduObrazkuEnum;
use Gamecon\Web\VysledekPrevoduObrazku;
use Gamecon\Web\ZpracovaneObrazky;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'obrazky:prevest-na-avif',
    description: 'Převede JPEG a PNG vložené do textů webu do AVIF a přepíše odkazy na ně; bez --provest jen vypíše, co by se stalo',
)]
class PrevestObrazkyNaAvifCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('provest', null, InputOption::VALUE_NONE, 'Skutečně zapsat. Bez něj se nic nezmění.')
            ->addOption('zaloha-hotova', null, InputOption::VALUE_NONE, 'Potvrzení, že existuje kopie web/soubory/obsah a export databáze. Nahrané soubory se nezálohují, bez kopie nejde převod vrátit.')
            ->addOption('prostredi', null, InputOption::VALUE_REQUIRED, 'Prostředí, na kterém se zapisuje (ostre, beta, preview, locale); musí souhlasit s tím, které příkaz sám pozná')
            ->addOption('smazat-puvodni', null, InputOption::VALUE_NONE, 'Místo převodu smazat originály, ke kterým je vedle stejný AVIF a na které už nic neodkazuje.')
            ->addOption('slozka', null, InputOption::VALUE_REQUIRED, 'Omezit běh na složku v soubory/obsah, například blog (pro zkoušku)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Převést nejvýš tolik obrázků (pro zkoušku)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // legacy bootstrap definuje konstanty (adresáře webu, kvalita AVIF) a databázi
        require_once __DIR__ . '/../../../nastaveni/zavadec-zaklad.php';

        $naostro = (bool) $input->getOption('provest');
        $smazat = (bool) $input->getOption('smazat-puvodni');
        $slozka = $input->getOption('slozka');
        $limit = $input->getOption('limit');
        if ($limit !== null && (! ctype_digit((string) $limit) || (int) $limit < 1)) {
            $io->error('Přepínač --limit chce kladné celé číslo.');

            return Command::FAILURE;
        }
        if ($limit !== null && $smazat) {
            // limit zná jen převod, mazání by ho tiše přeskočilo a smazalo všechno
            $io->error('Přepínač --limit jde jen s převodem, ne s --smazat-puvodni. Mazání jde omezit přepínačem --slozka.');

            return Command::FAILURE;
        }
        if ($naostro && ! $input->getOption('zaloha-hotova')) {
            $io->error('Převod se zapisuje do souborů a textů, které se nezálohují. Udělej kopii web/soubory/obsah a export databáze a spusť příkaz znovu s --zaloha-hotova.');

            return Command::FAILURE;
        }
        if (! $this->umiImagickAvif($io)) {
            return Command::FAILURE;
        }

        $io->title($smazat ? 'Mazání originálů převedených do AVIF' : 'Převod obrázků do AVIF');
        $prostredi = Prostredi::detect();
        $io->definitionList(
            [
                'Prostředí' => $prostredi->value,
            ],
            [
                'Databáze' => DB_NAME,
            ],
            [
                'Adresář s obrázky' => ZpracovaneObrazky::adresarObsahu(),
            ],
        );
        if ($naostro && $input->getOption('prostredi') !== $prostredi->value) {
            $io->error(sprintf(
                'Zapisovat jde jen na prostředí, které zadáš přepínačem --prostredi, a tenhle běh se hlásí k prostředí „%s“. Prostředí se pozná jako na webu, podle SERVER_NAME z nastaveni/nastaveni-server.php.',
                $prostredi->value,
            ));

            return Command::FAILURE;
        }
        $nesoulad = $naostro
            ? self::getNesouladProstredi($prostredi, $this->getAdresarProjektu(), $this->getUrlWebu())
            : null;
        if ($nesoulad !== null) {
            $io->error($nesoulad . ' Zkontroluj SERVER_NAME v nastaveni/nastaveni-server.php, nic se nezapsalo.');

            return Command::FAILURE;
        }
        $io->writeln($naostro
            ? '<comment>Ostrý běh: zapisuje se.</comment>'
            : '<info>Suchý běh: nic se nezapíše. Trvá stejně dlouho jako ostrý, úspora se zjišťuje zakódováním.</info>');

        set_time_limit(0);
        $prevod = new PrevodObrazkuNaAvif(
            new OptimalizaceObrazku(),
            new PouzitiObrazku(),
            ZpracovaneObrazky::adresarObsahu(),
            $slozka === null ? null : (string) $slozka,
        );
        $vysledek = $smazat
            ? $prevod->smazPuvodni($naostro)
            : $prevod->proved($naostro, $limit === null ? null : (int) $limit);

        $this->vypis($io, $vysledek, $naostro, $smazat);
        if ($naostro && ($vysledek->prepsanoRadku[AkceSeznamSqlStruktura::AKCE_SEZNAM_TABULKA] ?? 0) > 0) {
            // popisy aktivit jsou i v JSON programu, který se jinak obnoví až na dalším požadavku
            SystemoveNastaveni::zGlobals()->oznacProgramCacheJakoDirty();
        }

        return Command::SUCCESS;
    }

    protected function getAdresarProjektu(): string
    {
        return dirname(__DIR__, 3);
    }

    protected function getUrlWebu(): string
    {
        return URL_WEBU;
    }

    /**
     * Prostředí se při načítání nastavení vybralo dřív, než se doplnil výchozí `SERVER_NAME`, takže prázdný `SERVER_NAME`
     * z bety udělá ostrou a chybějící soubor z ostré `locale`. Beta běží v adresáři `beta` a nastavení, které se načetlo,
     * má adresu webu svého prostředí; obojí se musí shodovat s tím, co `Prostredi::detect()` hlásí teď.
     *
     * @return string|null popis nesouladu, null když je všechno v pořádku
     */
    public static function getNesouladProstredi(Prostredi $detekovane, string $adresarProjektu, string $urlWebu): ?string
    {
        if ((basename(rtrim($adresarProjektu, '/')) === 'beta') !== ($detekovane === Prostredi::Beta)) {
            return sprintf('Prostředí „%s“ nesedí k adresáři projektu „%s“: beta běží v adresáři `beta` a jinde ne.', $detekovane->value, basename(rtrim($adresarProjektu, '/')));
        }
        $podleAdresy = self::getProstrediPodleUrlWebu($urlWebu);
        // adresu, kterou nezná, smí mít jen lokální web vývojáře, a ne z domény GameConu (archiv, www, zrcadlo)
        $host = (string) parse_url($urlWebu, PHP_URL_HOST);
        $zDomenyGameconu = $host === 'gamecon.cz' || str_ends_with($host, '.gamecon.cz');
        if ($podleAdresy === null ? ($detekovane !== Prostredi::Locale || $zDomenyGameconu) : $podleAdresy !== $detekovane) {
            return sprintf('Načtené nastavení patří k prostředí „%s“ (adresa webu %s), ale běh se hlásí k prostředí „%s“.', $podleAdresy->value ?? 'neznámému', $urlWebu, $detekovane->value);
        }

        return null;
    }

    private static function getProstrediPodleUrlWebu(string $urlWebu): ?Prostredi
    {
        $host = (string) parse_url($urlWebu, PHP_URL_HOST);

        return match (true) {
            $host === 'beta.gamecon.cz'                              => Prostredi::Beta,
            $host === 'gamecon.cz'                                   => Prostredi::Production,
            in_array($host, ['localhost', '127.0.0.1', '::1'], true) => Prostredi::Locale,
            \jeHostPreview($host)                                    => Prostredi::Preview,
            default                                                  => null,
        };
    }

    /**
     * Zápis AVIF i s průhledností musí tenhle ImageMagick zvládnout a přečíst zpátky, jinak by se zapsaly rozbité soubory.
     */
    private function umiImagickAvif(SymfonyStyle $io): bool
    {
        $zkouska = ImagickAvif::provedZkousku();
        if ($zkouska['neprusvitny'] && $zkouska['prusvitny']) {
            return true;
        }
        $io->error(sprintf(
            'Tenhle ImageMagick nezapíše AVIF správně (bez průhlednosti: %s, s průhledností: %s), nic se nepřevádí.',
            $zkouska['neprusvitny'] ? 'ano' : 'ne',
            $zkouska['prusvitny'] ? 'ano' : 'ne',
        ));

        return false;
    }

    private function vypis(SymfonyStyle $io, VysledekPrevoduObrazku $vysledek, bool $naostro, bool $smazani): void
    {
        $prevedene = array_filter(
            $vysledek->polozky,
            static fn (PolozkaPrevoduObrazku $polozka): bool => in_array($polozka->stav, [StavPrevoduObrazkuEnum::PREVEDENO, StavPrevoduObrazkuEnum::PUVODNI_SMAZAN], true),
        );
        foreach ($vysledek->polozky as $polozka) {
            $uspesna = in_array($polozka, $prevedene, true);
            if ($uspesna && ! $io->isVerbose()) {
                continue;
            }
            $io->writeln(sprintf(
                '%s: %s%s%s',
                $polozka->cesta,
                $polozka->stav->getPopis(),
                $polozka->podrobnosti === '' ? '' : ' (' . $polozka->podrobnosti . ')',
                $polozka->stav === StavPrevoduObrazkuEnum::PREVEDENO
                    ? sprintf(' [%s → %s]', NahravaniObrazku::lidskaVelikost($polozka->puvodniBajtu), NahravaniObrazku::lidskaVelikost($polozka->novychBajtu))
                    : '',
            ));
        }

        $io->section('Souhrn');
        $radky = [];
        foreach ($vysledek->getPoctyPodleStavu() as $stav => $pocet) {
            $radky[] = [StavPrevoduObrazkuEnum::from($stav)->getPopis(), $pocet];
        }
        if (! $smazani) {
            $radky[] = ['obrázky, na které žádný text neodkazuje (nechány)', $vysledek->nepouzitychObrazku];
            $radky[] = ['obrázky, které texty zmiňují, ale na disku nejsou', $vysledek->odkazuNaChybejiciSoubor];
            foreach ($vysledek->prepsanoRadku as $tabulka => $pocet) {
                $radky[] = [sprintf('řádky tabulky %s %s', $tabulka, $naostro ? 'přepsané' : 'k přepsání'), $pocet];
            }
            $radky[] = ['řádky změněné souběžně (nepřepsané, spusť příkaz znovu)', $vysledek->radkuZmenenychSoubezne];
        }
        $io->table(['', 'počet'], $radky);
        if (! $smazani) {
            $uspora = $vysledek->getUsporaBajtu();
            $io->writeln(
                $uspora >= 0
                    ? sprintf('%s: %s', $naostro ? 'Ušetřeno' : 'Ušetřilo by se', NahravaniObrazku::lidskaVelikost($uspora))
                    : sprintf('%s: %s', $naostro ? 'Zvětšilo se o' : 'Zvětšilo by se o', NahravaniObrazku::lidskaVelikost(-$uspora)),
            );
        }
        if (! $naostro) {
            $io->note('Suchý běh, nic se nezměnilo. Zapsat se dá přepínačem --provest.');
        }
    }
}
