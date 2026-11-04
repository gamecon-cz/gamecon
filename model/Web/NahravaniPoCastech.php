<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Velký soubor z administrace přichází po částech o několika MB, aby ho nesvazovaly limity PHP, Apache a proxy
 * pro jeden požadavek a aby se při výpadku spojení opakovala jen jedna část. Části se skládají do mezisouboru
 * mimo web. Formulář pak místo souboru pošle jeho `id` a zpracuje ho stejnou cestou jako soubor z `$_FILES`,
 * viz @see NahravaniObrazku::overSoubor(). Protokol: @see NahravaniPoCastechPozadavek, prohlížeč: admin/files/nahravani-po-castech.js
 */
final class NahravaniPoCastech
{
    public const CAST_BAJTU = 4_194_304;
    private const MIN_CAST_BAJTU = 262_144;
    private const MAX_ROZPRACOVANYCH = 3;
    private const STARI_ROZPRACOVANEHO_SEKUND = 86_400;
    /** Rezerva na místo na disku nad velikost mezisouboru a jeho kopie na cílovém místě */
    private const REZERVA_MISTA_BAJTU = 104_857_600;
    /** Formulář s multipart tělem je o hlavičky a ostatní pole větší než soubor v něm */
    private const REZERVA_FORMULARE_BAJTU = 65_536;
    private const ZAMEK = '.zamek';
    private const DATA = 'data';
    private const METADATA = 'metadata.json';

    private readonly int $castBajtu;

    /**
     * @param int $idUzivatele nahrávání smí dokončit jen ten, kdo ho začal
     * @param int $maxVelikostBajtu strop velikosti celého souboru
     * @param string $adresar mimo web, `SPEC` je soukromá cache
     */
    public function __construct(
        private readonly int $idUzivatele,
        private readonly int $maxVelikostBajtu,
        private readonly string $adresar,
        ?int $castBajtu = null,
    ) {
        $this->castBajtu = $castBajtu ?? self::castBajtuPodlePhp();
    }

    public static function proUzivatele(int $idUzivatele): self
    {
        return new self(
            $idUzivatele,
            (int) round(SOUBOR_MAX_MB * 1024 * 1024),
            ADRESAR_NAHRAVANI_PO_CASTECH,
        );
    }

    /**
     * Část musí projít jedním požadavkem, takže ji omezuje `post_max_size`.
     */
    private static function castBajtuPodlePhp(): int
    {
        $postMaxSize = NahravaniObrazku::bajtyZNastaveniPhp('post_max_size');

        return $postMaxSize > 0
            ? max(self::MIN_CAST_BAJTU, min(self::CAST_BAJTU, intdiv($postMaxSize, 2)))
            : self::CAST_BAJTU;
    }

    public function getCastBajtu(): int
    {
        return $this->castBajtu;
    }

    /**
     * Soubor do téhle velikosti projde i obyčejným formulářem v jednom požadavku, větší se posílá po částech.
     * Je to menší z části a limitu PHP pro jeden požadavek, jinak by soubor mezi nimi vrátil chybu PHP, i když by po částech prošel.
     */
    public function getPrahBajtu(): int
    {
        $limity = array_filter([
            NahravaniObrazku::bajtyZNastaveniPhp('upload_max_filesize'),
            NahravaniObrazku::bajtyZNastaveniPhp('post_max_size'),
        ], static fn (int $limit): bool => $limit > 0);

        return $limity === []
            ? $this->castBajtu
            : max(0, min($this->castBajtu, min($limity) - self::REZERVA_FORMULARE_BAJTU));
    }

    /**
     * @return string `id` nového nahrávání
     */
    public function zahaj(int $velikostBajtu, string $puvodniNazev): string
    {
        if ($velikostBajtu <= 0) {
            throw new NahravaniObrazkuException('Soubor je prázdný.');
        }
        if ($velikostBajtu > $this->maxVelikostBajtu) {
            throw new NahravaniObrazkuException(
                sprintf('Soubor je příliš velký, maximum je %s.', NahravaniObrazku::lidskaVelikost($this->maxVelikostBajtu)),
            );
        }
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->adresar, 0775);
        // úklid, počet a založení pod zámkem: souběžné zahájení by jinak smazalo napůl založené nahrávání
        // nebo překročilo limit rozpracovaných
        $zamek = @fopen($this->adresar . '/' . self::ZAMEK, 'c');
        if ($zamek === false || !flock($zamek, LOCK_EX)) {
            throw new NahravaniObrazkuException('Nahrávání se nepodařilo zahájit, zkus to prosím znovu.');
        }
        try {
            $this->uklidStare($filesystem);
            if (count($this->getRozpracovanaNahraniUzivatele()) >= self::MAX_ROZPRACOVANYCH) {
                throw new NahravaniObrazkuException(
                    'Rozpracovaných nahrávání je moc. Počkej, až se dokončí, nebo to zkus za chvíli znovu.',
                );
            }
            $volneMisto = @disk_free_space($this->adresar);
            if ($volneMisto !== false && $volneMisto < 2 * $velikostBajtu + self::REZERVA_MISTA_BAJTU) {
                throw new NahravaniObrazkuException('Na serveru není pro tak velký soubor dost místa.');
            }

            $id = bin2hex(random_bytes(16));
            $adresarNahrani = $this->getAdresarNahrani($id);
            $filesystem->mkdir($adresarNahrani, 0775);
            $filesystem->dumpFile($adresarNahrani . '/' . self::METADATA, json_encode([
                'uzivatel'  => $this->idUzivatele,
                'velikost'  => $velikostBajtu,
                'nazev'     => $puvodniNazev,
                'castBajtu' => $this->castBajtu,
            ], JSON_THROW_ON_ERROR));
            $filesystem->touch($adresarNahrani . '/' . self::DATA);
        } finally {
            flock($zamek, LOCK_UN);
            fclose($zamek);
        }

        return $id;
    }

    /**
     * Zapíše část na její místo. Stejnou část jde poslat znovu, přepíše se, a tím přijde o všechny za ní.
     *
     * @param int $index pořadí části od nuly
     * @param resource $obsah tělo části
     * @return int kolik bajtů souboru je po zápisu přijato
     */
    public function prijmiCast(string $id, int $index, $obsah): int
    {
        $metadata = $this->getMetadata($id);
        $castBajtu = (int) $metadata['castBajtu'];
        $velikost = (int) $metadata['velikost'];
        $posun = $index * $castBajtu;
        if ($index < 0 || $posun >= $velikost) {
            throw new NahravaniObrazkuException('Tahle část souboru tam nepatří.');
        }
        $ocekavanaDelka = min($castBajtu, $velikost - $posun);

        $soubor = @fopen($this->getAdresarNahrani($id) . '/' . self::DATA, 'c+b');
        if ($soubor === false) {
            throw new NahravaniObrazkuException('Nahrávání se nepodařilo zapsat, zkus to prosím znovu.');
        }
        try {
            // opakování stejné části počká na první požadavek, který ji ještě zapisuje
            flock($soubor, LOCK_EX);
            clearstatcache(true, $this->getAdresarNahrani($id) . '/' . self::DATA);
            if ($posun > (int) fstat($soubor)['size']) {
                throw new NahravaniObrazkuException('Chybí předchozí část souboru, nahrávání se musí začít znovu.');
            }
            ftruncate($soubor, $posun);
            fseek($soubor, $posun);
            // o bajt víc, aby se poznala příliš dlouhá část
            $zapsano = (int) stream_copy_to_stream($obsah, $soubor, $ocekavanaDelka + 1);
            if ($zapsano !== $ocekavanaDelka) {
                ftruncate($soubor, $posun);
                throw new NahravaniObrazkuException(
                    sprintf('Část souboru má %d bajtů místo %d, nahrávání se musí začít znovu.', $zapsano, $ocekavanaDelka),
                );
            }
            fflush($soubor);
        } finally {
            flock($soubor, LOCK_UN);
            fclose($soubor);
        }

        return $posun + $ocekavanaDelka;
    }

    /**
     * Celý soubor, který je poslaný a čeká na zpracování. Smaže se až @see smaz()
     */
    public function dokonceny(string $id): NahranyPoCastech
    {
        $metadata = $this->getMetadata($id);
        $cesta = $this->getAdresarNahrani($id) . '/' . self::DATA;
        clearstatcache(true, $cesta);
        $prijato = (int) @filesize($cesta);
        if ($prijato !== (int) $metadata['velikost']) {
            throw new NahravaniObrazkuException(sprintf(
                'Soubor se nenahrál celý (%s z %s), zkus to prosím znovu.',
                NahravaniObrazku::lidskaVelikost($prijato),
                NahravaniObrazku::lidskaVelikost((int) $metadata['velikost']),
            ));
        }

        return new NahranyPoCastech($cesta, (string) $metadata['nazev'], $prijato);
    }

    /**
     * Celý poslaný soubor jako kterýkoli jiný nahraný: projde kontrolou služby, dál ho zpracuje volající.
     */
    public function over(string $id, NahravaniObrazku $nahravani): NahranyObrazek
    {
        $nahrany = $this->dokonceny($id);

        return $nahravani->overSoubor($nahrany->cesta, $nahrany->puvodniNazev);
    }

    /**
     * Provede akci formuláře a mezisoubor smaže, ať dopadla jak dopadla: `id` je na jedno použití a odmítnutý
     * formulář (špatná složka, neexistující cíl) by jinak nechal soubor ležet a zabíral by jedno z rozpracovaných.
     *
     * @template T
     * @param callable(): T $akce
     * @return T
     */
    public function sUklidem(string $id, callable $akce): mixed
    {
        try {
            return $akce();
        } finally {
            $this->smaz($id);
        }
    }

    public function smaz(string $id): void
    {
        if (!self::jePlatneId($id)) {
            return;
        }
        $adresarNahrani = $this->getAdresarNahrani($id);
        if (is_dir($adresarNahrani) && $this->patriUzivateli($adresarNahrani)) {
            (new Filesystem())->remove($adresarNahrani);
        }
    }

    /**
     * @return array{uzivatel: int, velikost: int, nazev: string, castBajtu: int}
     */
    private function getMetadata(string $id): array
    {
        if (!self::jePlatneId($id)) {
            throw new NahravaniObrazkuException('Neplatné nahrávání, zkus to prosím znovu.');
        }
        $adresarNahrani = $this->getAdresarNahrani($id);
        $metadata = $this->nactiMetadata($adresarNahrani);
        if ($metadata === null || (int) $metadata['uzivatel'] !== $this->idUzivatele) {
            // cizí nahrávání se tváří stejně jako neexistující
            throw new NahravaniObrazkuException('Nahrávání už neexistuje, zkus to prosím znovu.');
        }

        return $metadata;
    }

    /**
     * @return array{uzivatel: int, velikost: int, nazev: string, castBajtu: int}|null
     */
    private function nactiMetadata(string $adresarNahrani): ?array
    {
        $json = @file_get_contents($adresarNahrani . '/' . self::METADATA);
        if ($json === false) {
            return null;
        }
        $metadata = json_decode($json, true);

        return is_array($metadata)
            && isset($metadata['uzivatel'], $metadata['velikost'], $metadata['nazev'], $metadata['castBajtu'])
            ? $metadata
            : null;
    }

    private function patriUzivateli(string $adresarNahrani): bool
    {
        $metadata = $this->nactiMetadata($adresarNahrani);

        return $metadata !== null && (int) $metadata['uzivatel'] === $this->idUzivatele;
    }

    /**
     * @return list<string> adresáře nahrávání tohoto uživatele
     */
    private function getRozpracovanaNahraniUzivatele(): array
    {
        return array_values(array_filter(
            glob($this->adresar . '/*', GLOB_ONLYDIR) ?: [],
            fn (string $adresarNahrani): bool => $this->patriUzivateli($adresarNahrani),
        ));
    }

    /**
     * Nahrávání, na kterém se delší dobu nic nezměnilo, tedy zavřené okno nebo spadlé spojení.
     */
    private function uklidStare(Filesystem $filesystem): void
    {
        foreach (glob($this->adresar . '/*', GLOB_ONLYDIR) ?: [] as $adresarNahrani) {
            clearstatcache(true);
            // adresář sám je na začátku jediné, co existuje
            $posledniZmena = max(
                (int) @filemtime($adresarNahrani),
                (int) @filemtime($adresarNahrani . '/' . self::DATA),
                (int) @filemtime($adresarNahrani . '/' . self::METADATA),
            );
            if (time() - $posledniZmena > self::STARI_ROZPRACOVANEHO_SEKUND) {
                $filesystem->remove($adresarNahrani);
            }
        }
    }

    private function getAdresarNahrani(string $id): string
    {
        return $this->adresar . '/' . $id;
    }

    private static function jePlatneId(string $id): bool
    {
        return preg_match('~^[0-9a-f]{32}$~D', $id) === 1;
    }
}
