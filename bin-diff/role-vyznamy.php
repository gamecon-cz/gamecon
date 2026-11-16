<?php

declare(strict_types=1);

// Pouští se přes `porovnej-role.sh`, který skript zkopíruje do `symfony/var/` — odtud cesta
// k zavaděči sedí. Spuštěný rovnou z `bin-diff/` na ní spadne.
//
// Vypisuje počty, při rozdílu pár id uživatelů a zprávy výjimek, které mohou nést data z
// databáze. Repo je veřejné a běh je nad produkční zálohou, takže výstup nevkládat do PR
// ani issue.
require __DIR__ . '/../../nastaveni/zavadec.php';

use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\RoleMeaning;
use App\Repository\UserRoleRepository;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

$entityManager = SystemoveNastaveni::zGlobals()->getService('doctrine.orm.entity_manager');
// Repozitář není veřejná služba, přes `getRepository()` se k němu dá dostat i tak.
/** @var UserRoleRepository $repozitar */
$repozitar = $entityManager->getRepository(UserRole::class);

/**
 * @return array<int, list<string>> významy rolí po uživatelích, seřazené
 */
$vyznamyPodleUzivatele = static function (string $sql): array {
    $vysledek = [];
    foreach (dbFetchAll($sql) as $radek) {
        $vysledek[(int) $radek['id_uzivatele']][] = (string) $radek['vyznam_role'];
    }
    foreach ($vysledek as &$vyznamy) {
        sort($vyznamy);
    }

    return $vysledek;
};

// Totéž, co čte legacy: role přes pohled `platne_role_uzivatelu` (letošní, bez ročníku, účastnické).
$platne = $vyznamyPodleUzivatele(<<<'SQL'
    SELECT DISTINCT platne_role_uzivatelu.id_uzivatele, role_seznam.vyznam_role
    FROM platne_role_uzivatelu
    INNER JOIN role_seznam ON role_seznam.id_role = platne_role_uzivatelu.id_role
    SQL);
// Totéž, co dělala nová vrstva dřív: role všech ročníků z `uzivatele_role`.
$vsechny = $vyznamyPodleUzivatele(<<<'SQL'
    SELECT DISTINCT uzivatele_role.id_uzivatele, role_seznam.vyznam_role
    FROM uzivatele_role
    INNER JOIN role_seznam ON role_seznam.id_role = uzivatele_role.id_role
    SQL);

$pocet = [
    'uzivatelu'               => 0,
    'jen_minule_rocniky'      => 0,
    'rozdil_vyznamy'          => 0,
    'rozdil_organizator'      => 0,
    'nova_vrstva_spadla'      => 0,
    'nejdou_nacist'           => 0,
    'chyba_bez_podminky_roku' => 0,
];
$druhyChyb = [];
$prvniRozdily = [];

// Druhy chyb se klíčují místem a zprávou a tisknou se od nejčastějšího, nejvýš deset: zpráva
// s id uvnitř by jinak dala řádek na každého uživatele a přehled by zmizel.
$zapisChybu = static function (string $kde, Throwable $chyba, int $idUzivatele, string $citac) use (&$pocet, &$druhyChyb, &$prvniRozdily): void {
    ++$pocet[$citac];
    $druh = "[{$kde}] " . $chyba::class . ': ' . $chyba->getMessage();
    $druhyChyb[$druh] = ($druhyChyb[$druh] ?? 0) + 1;
    $prvniRozdily[] = $idUzivatele;
};

foreach (array_keys($vsechny) as $poradi => $idUzivatele) {
    ++$pocet['uzivatelu'];
    $ocekavane = $platne[$idUzivatele] ?? [];
    $uzivatel = $entityManager->getReference(User::class, $idUzivatele);

    if ($ocekavane === []) {
        ++$pocet['jen_minule_rocniky'];
    }
    if ($vsechny[$idUzivatele] !== $ocekavane) {
        ++$pocet['chyba_bez_podminky_roku'];
    }

    // Výjimka z nové vrstvy (třeba neznámý význam u platné role) je nález, ne důvod skript
    // ukončit bez přehledu. Každá kontrola má vlastní `try`, aby pád jedné nepřeskočil druhou.
    try {
        $nove = array_map(static fn (RoleMeaning $vyznam): string => $vyznam->value, $repozitar->getValidMeanings($uzivatel, ROCNIK));
        sort($nove);
        if ($nove !== $ocekavane) {
            ++$pocet['rozdil_vyznamy'];
            $prvniRozdily[] = $idUzivatele;
        }
    } catch (Throwable $chyba) {
        $zapisChybu('nová vrstva, významy', $chyba, $idUzivatele, 'nova_vrstva_spadla');
    }

    try {
        // `tryFrom`: neznámý význam v záloze nesmí shodit referenční stranu, ať se dá ohlásit níž.
        $ocekavanyOrganizator = RoleMeaning::anyIsOrganizer(array_filter(array_map(
            static fn (string $vyznam): ?RoleMeaning => RoleMeaning::tryFrom($vyznam),
            $ocekavane,
        )));
        if ($repozitar->isOrganizer($uzivatel, ROCNIK) !== $ocekavanyOrganizator) {
            ++$pocet['rozdil_organizator'];
            $prvniRozdily[] = $idUzivatele;
        }
    } catch (Throwable $chyba) {
        $zapisChybu('nová vrstva, organizátor', $chyba, $idUzivatele, 'nova_vrstva_spadla');
    }

    // Co dělá Symfony Security u každého přihlášeného: projde všechny role uživatele včetně
    // minulých ročníků, takže jediný neznámý význam (`MappingException` při načtení enumu) shodí
    // celý požadavek.
    try {
        $uzivatel->getRoles();
    } catch (Throwable $chyba) {
        $zapisChybu('načtení rolí', $chyba, $idUzivatele, 'nejdou_nacist');
    }

    if ($poradi % 200 === 199) {
        $entityManager->clear();
    }
}

$radek = static fn (string $popis, string|int $vysledek): string => mb_str_pad($popis, 62) . $vysledek . "\n";

echo $radek('uživatelů s nějakou rolí:', $pocet['uzivatelu']);
echo $radek('  z toho jen s rolemi minulých ročníků (letos nic):', $pocet['jen_minule_rocniky']);
echo $radek('významy platných rolí, nová vrstva × legacy pohled:', $pocet['rozdil_vyznamy'] === 0 ? 'shodují se' : "ROZDÍL u {$pocet['rozdil_vyznamy']}");
echo $radek('organizátor, nová vrstva × legacy pohled:', $pocet['rozdil_organizator'] === 0 ? 'shodují se' : "ROZDÍL u {$pocet['rozdil_organizator']}");
echo $radek('nová vrstva odpověděla každému (bez výjimky):', $pocet['nova_vrstva_spadla'] === 0 ? 'ano' : "NE, výjimek: {$pocet['nova_vrstva_spadla']}");
echo $radek('role jdou načíst jako entity (Security::getRoles):', $pocet['nejdou_nacist'] === 0 ? 'všem' : "NEJDOU u {$pocet['nejdou_nacist']}");
arsort($druhyChyb);
foreach (array_slice($druhyChyb, 0, 10, true) as $druh => $kolikrat) {
    echo "    {$kolikrat}× {$druh}\n";
}
if (count($druhyChyb) > 10) {
    echo '    … a dalších ' . (count($druhyChyb) - 10) . " druhů chyb\n";
}
echo $radek('pro srovnání: významy by se lišily bez podmínky ročníku u:', $pocet['chyba_bez_podminky_roku']);

// Skutečný rozdíl se hlásí vždycky, i když je databáze jinak nevhodná.
if ($pocet['rozdil_vyznamy'] + $pocet['rozdil_organizator'] + $pocet['nova_vrstva_spadla'] + $pocet['nejdou_nacist'] > 0) {
    $prvniRozdily = array_values(array_unique($prvniRozdily));
    echo "\n✗ Rozdíly. První uživatelé (id): " . implode(', ', array_slice($prvniRozdily, 0, 5)) . "\n";
    exit(1);
}

// Kontrola, která nemá co zkontrolovat, nesmí vypadat jako zelená: bez rolí minulých
// ročníků by se nepoznalo, že se podmínka ročníku vůbec uplatňuje.
if ($pocet['uzivatelu'] === 0 || $pocet['chyba_bez_podminky_roku'] === 0) {
    echo "\n✗ Databáze nemá uživatele s rolí z minulého ročníku — kontrola nic neprokazuje.\n";
    echo "  Spusť ji nad zálohou produkce (bin/stahni-zalohu-db.sh, bin/import-db.sh).\n";
    exit(2);
}

echo "\n✓ Nová vrstva čte role stejně jako legacy u všech uživatelů.\n";
