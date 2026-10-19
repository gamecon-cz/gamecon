<?php

declare(strict_types=1);

// Pouští se přes `porovnej-role.sh`, který skript zkopíruje do `symfony/var/` — odtud cesta
// k zavaděči sedí. Spuštěný rovnou z `bin-diff/` na ní spadne.
//
// Vypisuje jen počty. Při rozdílu přidá pár id uživatelů, aby šlo hledat — repo je veřejné
// a běh je nad produkční zálohou, takže výstup nevkládat do PR ani issue.
require __DIR__ . '/../../nastaveni/zavadec.php';

use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\RoleMeaning;
use App\Repository\UserRoleRepository;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

$kontejner = SystemoveNastaveni::zGlobals()->kernel()->getContainer();
$entityManager = $kontejner->get('doctrine.orm.entity_manager');
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
    'nejdou_nacist'           => 0,
    'chyba_bez_podminky_roku' => 0,
];
$druhyChyb = [];
$prvniRozdily = [];

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

    $nove = array_map(static fn (RoleMeaning $vyznam): string => $vyznam->value, $repozitar->validMeanings($uzivatel, ROCNIK));
    sort($nove);
    if ($nove !== $ocekavane) {
        ++$pocet['rozdil_vyznamy'];
        $prvniRozdily[] = $idUzivatele;
    }

    // `tryFrom`: neznámý význam v záloze nesmí shodit referenční stranu, ať se dá ohlásit níž.
    $ocekavanyOrganizator = RoleMeaning::anyIsOrganizer(array_filter(array_map(
        static fn (string $vyznam): ?RoleMeaning => RoleMeaning::tryFrom($vyznam),
        $ocekavane,
    )));
    if ($repozitar->isOrganizer($uzivatel, ROCNIK) !== $ocekavanyOrganizator) {
        ++$pocet['rozdil_organizator'];
        $prvniRozdily[] = $idUzivatele;
    }

    // Co dělá Symfony Security u každého přihlášeného: projde všechny role uživatele včetně
    // minulých ročníků, takže jediný neznámý význam (`MappingException` při načtení enumu) shodí
    // celý požadavek.
    try {
        $uzivatel->getRoles();
    } catch (Throwable $chyba) {
        ++$pocet['nejdou_nacist'];
        $druh = $chyba::class . ': ' . $chyba->getMessage();
        $druhyChyb[$druh] = ($druhyChyb[$druh] ?? 0) + 1;
        $prvniRozdily[] = $idUzivatele;
    }

    if ($poradi % 200 === 199) {
        $entityManager->clear();
    }
}

echo "uživatelů s nějakou rolí:                                   {$pocet['uzivatelu']}\n";
echo "  z toho jen s rolemi minulých ročníků (letos nic):        {$pocet['jen_minule_rocniky']}\n";
echo 'významy platných rolí, nová vrstva × legacy pohled:        '
    . ($pocet['rozdil_vyznamy'] === 0 ? 'shodují se' : "ROZDÍL u {$pocet['rozdil_vyznamy']}") . "\n";
echo 'organizátor, nová vrstva × legacy pohled:                  '
    . ($pocet['rozdil_organizator'] === 0 ? 'shodují se' : "ROZDÍL u {$pocet['rozdil_organizator']}") . "\n";
echo 'role jdou načíst jako entity (Security::getRoles):          '
    . ($pocet['nejdou_nacist'] === 0 ? 'všem' : "NEJDOU u {$pocet['nejdou_nacist']}") . "\n";
foreach ($druhyChyb as $druh => $kolikrat) {
    echo "    {$kolikrat}× {$druh}\n";
}
echo "pro srovnání: významy by se lišily bez podmínky ročníku u:  {$pocet['chyba_bez_podminky_roku']}\n";

// Kontrola, která nemá co zkontrolovat, nesmí vypadat jako zelená: bez rolí minulých
// ročníků by se nepoznalo, že se podmínka ročníku vůbec uplatňuje.
if ($pocet['uzivatelu'] === 0 || $pocet['chyba_bez_podminky_roku'] === 0) {
    echo "\n✗ Databáze nemá uživatele s rolí z minulého ročníku — kontrola nic neprokazuje.\n";
    echo "  Spusť ji nad zálohou produkce (bin/stahni-zalohu-db.sh, bin/import-db.sh).\n";
    exit(2);
}

if ($pocet['rozdil_vyznamy'] + $pocet['rozdil_organizator'] + $pocet['nejdou_nacist'] > 0) {
    $prvniRozdily = array_values(array_unique($prvniRozdily));
    echo "\n✗ Rozdíly. První uživatelé (id): " . implode(', ', array_slice($prvniRozdily, 0, 5)) . "\n";
    exit(1);
}

echo "\n✓ Nová vrstva čte role stejně jako legacy u všech uživatelů.\n";
