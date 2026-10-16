<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\Exceptions\NeznamaEmailovaSablona;
use Gamecon\Kanaly\GcMail;

/**
 * All e-mails that come from templates and the variables each one can take; subject and text are in the table `emailova_sablona`.
 * Outside the catalog are the custom reminder wording and technical alerts, which must go out even without a database.
 *
 * @see \Gamecon\Uzivatel\UpominkaVlastniZneni::definice()
 */
class EmailoveSablonyKatalog
{
    private const SKUPINA_UCET        = 'Účet a registrace';
    private const SKUPINA_AKTIVITY    = 'Aktivity';
    private const SKUPINA_ODHLASOVANI = 'Odhlašování z GameConu';
    private const SKUPINA_UPOMINKY    = 'Upomínky dlužníkům';
    private const SKUPINA_PROMLCENI   = 'Promlčení zůstatků';
    private const SKUPINA_CFO         = 'Zprávy pro CFO';
    private const SKUPINA_TYM         = 'Zprávy pro tým (info@)';
    private const SKUPINA_REPORTY     = 'Reporty';

    /** @var array<string, EmailovaSablonaDefinice>|null */
    private static ?array $definice = null;

    /**
     * @return array<string, EmailovaSablonaDefinice> by code, in the same order as the list in the admin
     */
    public static function vsechny(): array
    {
        if (self::$definice === null) {
            self::$definice = [];
            foreach (self::sestav() as $definice) {
                if (isset(self::$definice[$definice->kod])) {
                    throw new \LogicException("Šablona '{$definice->kod}' je v katalogu dvakrát.");
                }
                self::$definice[$definice->kod] = $definice;
            }
        }

        return self::$definice;
    }

    public static function dej(string $kod): EmailovaSablonaDefinice
    {
        return self::vsechny()[$kod]
            ?? throw new NeznamaEmailovaSablona("Neznámá šablona e-mailu '$kod'.");
    }

    public static function existuje(string $kod): bool
    {
        return isset(self::vsechny()[$kod]);
    }

    /**
     * @return list<EmailovaSablonaDefinice>
     */
    private static function sestav(): array
    {
        return [
            ...self::ucetARegistrace(),
            ...self::aktivity(),
            ...self::odhlasovani(),
            ...self::upominky(),
            ...self::promlceni(),
            ...self::zpravyProCfo(),
            ...self::zpravyProTym(),
            ...self::reporty(),
        ];
    }

    /**
     * @return list<EmailovaSablonaDefinice>
     */
    private static function ucetARegistrace(): array
    {
        return [
            new EmailovaSablonaDefinice(
                kod: 'obnova_hesla',
                skupina: self::SKUPINA_UCET,
                nazev: 'Obnova hesla',
                kdyOdchazi: 'Když někdo na webu požádá o zapomenuté heslo.',
                prijemce: 'kdo o obnovu hesla požádal',
                format: GcMail::FORMAT_HTML,
                promenne: [
                    new EmailovaPromenna(
                        nazev: 'odkaz',
                        popis: 'odkaz, přes který si nastaví nové heslo (platí hodinu)',
                        ukazka: 'https://gamecon.cz/obnova-hesla?token=ukazkovy-token',
                    ),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'rychla_registrace',
                skupina: self::SKUPINA_UCET,
                nazev: 'Rychlá registrace',
                kdyOdchazi: 'Když infopult zaregistruje účastníka na místě a ten dostane odkaz na dokončení registrace.',
                prijemce: 'nově zaregistrovaný účastník',
                format: GcMail::FORMAT_HTML,
                promenne: [
                    new EmailovaPromenna(
                        nazev: 'odkaz_k_potvrzeni',
                        popis: 'odkaz, kterým účastník dokončí registraci a nastaví si přezdívku a heslo',
                        ukazka: 'https://gamecon.cz/potvrzeni-registrace/ukazkovy-kod',
                    ),
                ],
            ),
        ];
    }

    /**
     * @return list<EmailovaSablonaDefinice>
     */
    private static function aktivity(): array
    {
        return [
            new EmailovaSablonaDefinice(
                kod: 'uvolnene_misto',
                skupina: self::SKUPINA_AKTIVITY,
                nazev: 'Uvolnilo se místo na sledované aktivitě',
                kdyOdchazi: 'Když se na aktivitě, kterou účastník sleduje, uvolní místo (odhlásil se někdo jiný).',
                prijemce: 'všichni, kdo aktivitu sledují',
                format: GcMail::FORMAT_HTML,
                promenne: [
                    self::nazevAktivity(),
                    new EmailovaPromenna(
                        nazev: 'cas',
                        popis: 'kdy se aktivita koná (den a hodiny)',
                        ukazka: 'čtvrtek 10–14',
                    ),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'nedostaveni_se_na_aktivitu',
                skupina: self::SKUPINA_AKTIVITY,
                nazev: 'Nedostavení se na aktivitu',
                kdyOdchazi: 'Když vypravěč při zavírání prezence označí účastníka jako nedorazivšího (jen u typů aktivit, kde se to řeší, a jen během GameConu).',
                prijemce: 'účastník, který na aktivitu nedorazil',
                format: GcMail::FORMAT_HTML,
                promenne: [
                    self::nazevAktivity(),
                    new EmailovaPromenna(
                        nazev: 'lhuta_bez_pokuty',
                        popis: 'jak dlouho před začátkem aktivity se šlo odhlásit bez pokuty, slovy („hodinu“, „2 hodiny“, „6 hodin“)',
                        ukazka: '2 hodiny',
                    ),
                    new EmailovaPromenna(
                        nazev: 'pokuta_procenta',
                        popis: 'kolik procent ceny aktivity zaplatí ten, kdo se odhlásí později',
                        ukazka: '50',
                    ),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'uzavreni_prezence',
                skupina: self::SKUPINA_AKTIVITY,
                nazev: 'Uzavři prosím prezenci',
                kdyOdchazi: 'Když vypravěč po skončení aktivity nemá uzavřenou prezenci.',
                prijemce: 'vypravěč (nebo partner), který aktivity vedl',
                format: GcMail::FORMAT_HTML,
                promenne: [
                    new EmailovaPromenna(
                        nazev: 'na_aktivitach',
                        popis: 'kolik aktivit se týká, slovy: „aktivitě“ nebo třeba „3 aktivitách“',
                        ukazka: '2 aktivitách',
                        vPredmetu: true,
                    ),
                    new EmailovaPromenna(
                        nazev: 'aktivity',
                        popis: 'seznam neuzavřených aktivit, každá na jednom řádku s odkazem do prezence',
                        ukazka: '<a href="https://gamecon.cz/admin">Hrob krále</a> (skončila 16. 7. 2026 14:00)',
                        html: true,
                    ),
                ],
            ),
        ];
    }

    /**
     * @return list<EmailovaSablonaDefinice>
     */
    private static function odhlasovani(): array
    {
        return [
            new EmailovaSablonaDefinice(
                kod: 'odhlaseni_z_gameconu',
                skupina: self::SKUPINA_ODHLASOVANI,
                nazev: 'Odhlášení z GameConu',
                kdyOdchazi: 'Když organizátor (ne účastník sám) odhlásí účastníka z letošního GameConu.',
                prijemce: 'odhlášený účastník',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    self::koncovka(),
                    new EmailovaPromenna(
                        nazev: 'dalsi_vlna',
                        popis: 'kdy je nejbližší vlna přihlašování aktivit',
                        ukazka: '17. 7. 2026 20:00:00',
                    ),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'hromadne_odhlaseni_neplatic_zruseno',
                skupina: self::SKUPINA_ODHLASOVANI,
                nazev: 'Hromadné odhlášení neplatiče: co se zrušilo',
                kdyOdchazi: 'Při hromadném odhlášení neplatičů, těm, kterým se zrušily objednávky nebo aktivity.',
                prijemce: 'odhlášený neplatič',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    new EmailovaPromenna(
                        nazev: 'co',
                        popis: 'co se zrušilo: „objednávky“, „aktivity“ nebo „objednávky a aktivity“',
                        ukazka: 'objednávky a aktivity',
                        vPredmetu: true,
                    ),
                    new EmailovaPromenna(
                        nazev: 'zruseno',
                        popis: 'věta o tom, co přesně se zrušilo, včetně názvů',
                        ukazka: 'zrušit tvé objednávky Tričko, Kostka a odlásit z aktivit Hrob krále',
                    ),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'varovani_brzke_odhlaseni_neplatic',
                skupina: self::SKUPINA_ODHLASOVANI,
                nazev: 'Varování neplatiče před hromadným odhlášením',
                kdyOdchazi: 'Tři dny před hromadným odhlášením neplatičů, každému, komu odhlášení hrozí.',
                prijemce: 'neplatič, kterému hrozí odhlášení',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    new EmailovaPromenna(
                        nazev: 'jmeno',
                        popis: 'jméno a přezdívka příjemce',
                        ukazka: 'Jana „Jája“ Nováková',
                    ),
                ],
            ),
        ];
    }

    /**
     * Four variants by how the debtor took part this year, times two by how long after GameCon the reminder goes out.
     * The text used to be assembled from pieces by participation, so each variant is now the whole text, visible in the admin as it goes out.
     *
     * @return list<EmailovaSablonaDefinice>
     */
    private static function upominky(): array
    {
        $definice = [];
        foreach ([
            'tyden' => ['týden po skončení GameConu', 'Upomínka týden po GC'],
            'mesic' => ['měsíc po skončení GameConu a při ručním rozeslání „jako měsíční“', 'Upomínka měsíc po GC'],
        ] as $typ => [$kdy, $nazevTypu]) {
            foreach ([
                'pritomen'       => ['letos na GC byl', 'dlužník, který letos na GC byl'],
                'jen_prihlasen'  => ['se letos přihlásil, ale nedorazil', 'dlužník, který se letos přihlásil, ale nedorazil'],
                'nedorazil_drive' => ['letos nebyl, ale dřív na GC byl (dluh z minulých let)', 'dlužník, který letos nebyl, ale dřív na GC byl'],
                'nedorazil_nikdy' => ['na GC nikdy nebyl a dluh má z nedokončené přihlášky', 'dlužník, který na GC nikdy nebyl'],
            ] as $ucast => [$popisUcasti, $prijemce]) {
                $promenne = [
                    new EmailovaPromenna(
                        nazev: 'dluh',
                        popis: 'dlužná částka v korunách',
                        ukazka: '350',
                    ),
                    new EmailovaPromenna(
                        nazev: 'vs',
                        popis: 'variabilní symbol platby (ID účastníka)',
                        ukazka: '1234',
                    ),
                    new EmailovaPromenna(
                        nazev: 'jmeno',
                        popis: 'jméno a přezdívka příjemce',
                        ukazka: 'Jana „Jája“ Nováková',
                        volitelna: true,
                    ),
                    self::koncovka(vPredmetu: false, volitelna: $ucast !== 'jen_prihlasen'),
                ];
                if ($ucast === 'nedorazil_drive') {
                    $promenne[] = new EmailovaPromenna(
                        nazev: 'rok_posledni_ucasti',
                        popis: 'rok posledního GameConu, kterého se zúčastnil',
                        ukazka: '2023',
                    );
                }
                $definice[] = new EmailovaSablonaDefinice(
                    kod: "upominka_{$typ}_{$ucast}",
                    skupina: self::SKUPINA_UPOMINKY,
                    nazev: "$nazevTypu: $prijemce",
                    kdyOdchazi: "Automaticky $kdy, dlužníkovi, který $popisUcasti. QR kódy pro platbu jdou v příloze.",
                    prijemce: $prijemce,
                    format: GcMail::FORMAT_TEXT,
                    promenne: $promenne,
                );
            }
        }

        return $definice;
    }

    /**
     * @return list<EmailovaSablonaDefinice>
     */
    private static function promlceni(): array
    {
        $spolecne = [
            new EmailovaPromenna(
                nazev: 'jmeno',
                popis: 'jméno a přezdívka příjemce',
                ukazka: 'Jana „Jája“ Nováková',
            ),
            new EmailovaPromenna(
                nazev: 'zustatek',
                popis: 'zůstatek v korunách',
                ukazka: '1250',
                vPredmetu: true,
            ),
            new EmailovaPromenna(
                nazev: 'posledni_roky',
                popis: 'za jak dlouho se neúčastnil, správně sklonované („posledních 5 let“)',
                ukazka: 'posledních 5 let',
            ),
            self::koncovka(vPredmetu: false),
        ];

        return [
            new EmailovaSablonaDefinice(
                kod: 'promlceni_varovani_mesic',
                skupina: self::SKUPINA_PROMLCENI,
                nazev: 'Varování před promlčením zůstatku (měsíc před)',
                kdyOdchazi: 'Měsíc před otevřením registrací, každému s nevyužitým zůstatkem, který se několik let nezúčastnil.',
                prijemce: 'účastník s nevyužitým zůstatkem',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    ...$spolecne,
                    new EmailovaPromenna(
                        nazev: 'registrace_od',
                        popis: 'kdy začínají registrace, jen datum',
                        ukazka: '12.05.2026',
                    ),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'promlceni_varovani_tyden',
                skupina: self::SKUPINA_PROMLCENI,
                nazev: 'Připomínka promlčení zůstatku (týden před)',
                kdyOdchazi: 'Týden před otevřením registrací, stejným lidem jako měsíční varování.',
                prijemce: 'účastník s nevyužitým zůstatkem',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    ...$spolecne,
                    new EmailovaPromenna(
                        nazev: 'registrace_od',
                        popis: 'kdy začínají registrace, den a čas',
                        ukazka: 'úterý 12. 5. 2026 v 21:25',
                    ),
                ],
            ),
        ];
    }

    /**
     * @return list<EmailovaSablonaDefinice>
     */
    private static function zpravyProCfo(): array
    {
        $definice = [
            new EmailovaSablonaDefinice(
                kod: 'cfo_brzke_odhlaseni_neplaticu',
                skupina: self::SKUPINA_CFO,
                nazev: 'Brzy se hromadně odhlásí neplatiči',
                kdyOdchazi: 'Hodinu, den a týden před hromadným odhlášením neplatičů, se seznamem, koho se odhlášení týká.',
                prijemce: 'CFO',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    self::kdyBudeOdhlaseni(),
                    new EmailovaPromenna(
                        nazev: 'pocet',
                        popis: 'kolik účastníků se odhlásí',
                        ukazka: '12',
                        vPredmetu: true,
                    ),
                    new EmailovaPromenna(
                        nazev: 'seznam',
                        popis: 'řádek za každého neplatiče a případně položky shopu se špatným koncem prodeje',
                        ukazka: "Účastník 'Jája' (123) bude zítra odhlášen, protože má kategorii neplatiče 2",
                    ),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'cfo_nesparovane_platby',
                skupina: self::SKUPINA_CFO,
                nazev: 'Před hromadným odhlášením máme nespárované platby',
                kdyOdchazi: 'Den a týden před hromadným odhlášením neplatičů, pokud jsou nespárované platby.',
                prijemce: 'CFO',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    self::kdyBudeOdhlaseni(),
                    new EmailovaPromenna(
                        nazev: 'pocet',
                        popis: 'kolik je nespárovaných plateb',
                        ukazka: '3',
                        vPredmetu: true,
                    ),
                    new EmailovaPromenna(
                        nazev: 'seznam',
                        popis: 'řádek za každou nespárovanou platbu',
                        ukazka: 'Nespárovaná platba s FIO ID 1234567 s částkou 500 ze dne 2026-07-01',
                    ),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'cfo_neplatici_varovani_odeslano',
                skupina: self::SKUPINA_CFO,
                nazev: 'Neplatiči dostali varování před odhlášením',
                kdyOdchazi: 'Když se rozešlou varování neplatičům před hromadným odhlášením.',
                prijemce: 'CFO',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    new EmailovaPromenna(
                        nazev: 'pocet',
                        popis: 'kolika neplatičům varování odešlo',
                        ukazka: '12',
                        vPredmetu: true,
                    ),
                    new EmailovaPromenna(
                        nazev: 'priklad_predmet',
                        popis: 'předmět jednoho z odeslaných e-mailů',
                        ukazka: 'Nezaplacené objednávky Gamecon 2026',
                    ),
                    new EmailovaPromenna(
                        nazev: 'priklad_text',
                        popis: 'text jednoho z odeslaných e-mailů',
                        ukazka: 'Ahoj Jája, zaplať prosím všechny své objednávky…',
                    ),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'cfo_odhlaseni_neplaticu_provedeno',
                skupina: self::SKUPINA_CFO,
                nazev: 'Neplatiči byli hromadně odhlášeni',
                kdyOdchazi: 'Po automatickém hromadném odhlášení neplatičů.',
                prijemce: 'CFO',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    new EmailovaPromenna(
                        nazev: 'pocet',
                        popis: 'kolik účastníků se odhlásilo',
                        ukazka: '12',
                        vPredmetu: true,
                    ),
                    new EmailovaPromenna(
                        nazev: 'zaznamy',
                        popis: 'výpis toho, co se komu zrušilo',
                        ukazka: 'Odhlášené objednávky: …',
                    ),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'cfo_podezrele_vysoka_platba',
                skupina: self::SKUPINA_CFO,
                nazev: 'Podezřele vysoká platba',
                kdyOdchazi: 'Když se platba z banky automaticky spáruje s účastníkem a je vyšší než hranice v Nastavení.',
                prijemce: 'CFO',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    new EmailovaPromenna('castka', 'částka platby', '12 500,00'),
                    new EmailovaPromenna('vs', 'variabilní symbol platby', '1234'),
                    new EmailovaPromenna('datum', 'kdy platba přišla', '01.07.2026 10:15'),
                    new EmailovaPromenna('protiucet', 'název protiúčtu', 'Jan Novák'),
                    new EmailovaPromenna('id_uzivatele', 'ID účastníka, ke kterému se platba spárovala', '123'),
                    new EmailovaPromenna('jmeno_uzivatele', 'jméno a přezdívka účastníka', 'Jana „Jája“ Nováková'),
                    new EmailovaPromenna('email_uzivatele', 'e-mail účastníka', 'jana@example.test'),
                    new EmailovaPromenna('odkaz_uzivatel', 'odkaz na účastníka v adminu', 'https://admin.gamecon.cz/uzivatel?pracovni_uzivatel=123'),
                    new EmailovaPromenna('odkaz_nesparovane', 'odkaz na stránku nespárovaných plateb', 'https://admin.gamecon.cz/finance/nesparovane-platby'),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'cfo_promlceni_nikdo',
                skupina: self::SKUPINA_CFO,
                nazev: 'Promlčení zůstatků: nikdo k promlčení',
                kdyOdchazi: 'Po skončení GameConu, když automatické promlčení nenašlo nikoho, komu promlčet.',
                prijemce: 'CFO',
                format: GcMail::FORMAT_TEXT,
                promenne: [self::gcSkoncil()],
            ),
            new EmailovaSablonaDefinice(
                kod: 'cfo_promlceni_pred',
                skupina: self::SKUPINA_CFO,
                nazev: 'Promlčení zůstatků: report před promlčením',
                kdyOdchazi: 'Po skončení GameConu těsně před automatickým promlčením, s přílohou se seznamem.',
                prijemce: 'CFO',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    new EmailovaPromenna('pocet_uzivatelu', 'kolika uživatelům se zůstatek promlčí', '35'),
                    new EmailovaPromenna('suma', 'kolik korun se celkem promlčí', '12500'),
                    self::gcSkoncil(),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'cfo_promlceni_dokonceno',
                skupina: self::SKUPINA_CFO,
                nazev: 'Promlčení zůstatků: dokončeno',
                kdyOdchazi: 'Po automatickém promlčení, s přílohou se zůstatky všech uživatelů.',
                prijemce: 'CFO',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    new EmailovaPromenna('pocet', 'kolika uživatelům se zůstatek promlčel', '35'),
                    new EmailovaPromenna('suma', 'kolik korun se celkem promlčelo', '12500'),
                    self::gcSkoncil(),
                ],
            ),
        ];

        foreach ([
            'mesic' => ['Měsíc před registracemi'],
            'tyden' => ['Týden před registracemi'],
        ] as $typ => [$kdy]) {
            $definice[] = new EmailovaSablonaDefinice(
                kod: "cfo_promlceni_varovani_{$typ}_odeslano",
                skupina: self::SKUPINA_CFO,
                nazev: "Promlčení zůstatků: varování rozeslána ($kdy)",
                kdyOdchazi: 'Po rozeslání varování o promlčení zůstatků účastníkům.',
                prijemce: 'CFO',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    new EmailovaPromenna('pocet', 'kolika účastníkům varování odešlo', '35', vPredmetu: true),
                    new EmailovaPromenna('registrace_od', 'kdy začínají registrace', '12.05.2026 21:25'),
                    new EmailovaPromenna('pocet_let', 'po kolika letech neúčasti se zůstatek promlčuje', '5'),
                ],
            );
        }

        foreach ([
            'tyden'   => 'týden po GameConu',
            'mesic'   => 'měsíc po GameConu',
            'rucni'   => 'ruční rozeslání s měsíčním textem',
            'vlastni' => 'vlastní znění',
        ] as $typ => $kdy) {
            $definice[] = new EmailovaSablonaDefinice(
                kod: "cfo_upominky_{$typ}_odeslano",
                skupina: self::SKUPINA_CFO,
                nazev: "Upomínky dlužníkům rozeslány ($kdy)",
                kdyOdchazi: 'Po rozeslání upomínek dlužníkům, s ukázkou posledního odeslaného e-mailu.',
                prijemce: 'CFO',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    new EmailovaPromenna('pocet', 'kolika dlužníkům upomínka odešla', '120', vPredmetu: true),
                    new EmailovaPromenna('pocet_preskocenych', 'kolika dlužníkům se upomínka neposlala, protože ji už dostali', '0'),
                    new EmailovaPromenna('pocet_pod_prahem', 'kolik dlužníků se přeskočilo, protože dluží méně než práh', '8'),
                    new EmailovaPromenna('prah', 'práh v korunách, pod kterým se neupomíná', '251'),
                    new EmailovaPromenna('konec_gc', 'kdy skončil GameCon', '19.07.2026 21:00'),
                    new EmailovaPromenna('priklad_predmet', 'předmět jednoho z odeslaných e-mailů', 'GameCon 2026 - nedoplatky'),
                    new EmailovaPromenna('priklad_text', 'text jednoho z odeslaných e-mailů', 'Ahoj! …'),
                ],
            );
        }

        return $definice;
    }

    /**
     * @return list<EmailovaSablonaDefinice>
     */
    private static function zpravyProTym(): array
    {
        $odhlaseni = new EmailovaPromenna(
            nazev: 'odhlaseni',
            popis: '„se odhlásil“ (odhlásil se sám) nebo „byl odhlášen“ (odhlásil ho organizátor)',
            ukazka: 'byl odhlášen',
            vPredmetu: true,
        );
        $jmenoNick = new EmailovaPromenna(
            nazev: 'jmeno_nick',
            popis: 'přezdívka odhlášeného uživatele',
            ukazka: 'Jája',
            vPredmetu: true,
        );
        $idUzivatele = new EmailovaPromenna(
            nazev: 'id_uzivatele',
            popis: 'ID odhlášeného uživatele',
            ukazka: '123',
            vPredmetu: true,
        );

        return [
            new EmailovaSablonaDefinice(
                kod: 'info_odhlasil_ale_platil',
                skupina: self::SKUPINA_TYM,
                nazev: 'Odhlášený účastník už platil',
                kdyOdchazi: 'Když se odhlásí (nebo je odhlášen) účastník, který už letos něco zaplatil.',
                prijemce: 'info@gamecon.cz',
                format: GcMail::FORMAT_HTML,
                promenne: [
                    $jmenoNick,
                    $idUzivatele,
                    $odhlaseni,
                    new EmailovaPromenna('castka', 'kolik letos poslal na účet GameConu (v korunách)', '2000'),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'info_odhlasil_mel_objednavky',
                skupina: self::SKUPINA_TYM,
                nazev: 'Odhlášený účastník měl objednávky',
                kdyOdchazi: 'Když se odhlásí (nebo je odhlášen) účastník, který měl letos objednávky (ubytování, jídlo, trička…).',
                prijemce: 'info@gamecon.cz',
                format: GcMail::FORMAT_HTML,
                promenne: [
                    $jmenoNick,
                    $idUzivatele,
                    $odhlaseni,
                    new EmailovaPromenna(
                        nazev: 'objednavky',
                        popis: 'přehled objednávek, každá na jednom řádku',
                        ukazka: 'Ubytování: 3 noci<br>Jídlo: 6 jídel',
                        html: true,
                    ),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'info_hromadna_aktivace_aktivit',
                skupina: self::SKUPINA_TYM,
                nazev: 'Hromadně se aktivovaly aktivity',
                kdyOdchazi: 'Když cron na začátku vlny hromadně aktivuje aktivity.',
                prijemce: 'info@gamecon.cz',
                format: GcMail::FORMAT_TEXT,
                promenne: [
                    new EmailovaPromenna('pocet', 'kolik aktivit se aktivovalo', '85', vPredmetu: true),
                    new EmailovaPromenna('komplikace', 'popis komplikací, nebo prázdné, když žádné nebyly', 'U některých se vyskytly komplikace: …'),
                ],
            ),
        ];
    }

    /**
     * @return list<EmailovaSablonaDefinice>
     */
    private static function reporty(): array
    {
        return [
            new EmailovaSablonaDefinice(
                kod: 'bfgr_report',
                skupina: self::SKUPINA_REPORTY,
                nazev: 'BFGR report',
                kdyOdchazi: 'Když si někdo v adminu vyžádá BFGR report a ten se vygeneruje (zpráva má report v příloze).',
                prijemce: 'kdo report vyžádal',
                format: GcMail::FORMAT_HTML,
                promenne: [
                    new EmailovaPromenna('jmeno', 'jméno toho, kdo report vyžádal', 'Jana Nováková'),
                    new EmailovaPromenna('rocnik_reportu', 'ročník, pro který je report', '2026', vPredmetu: true),
                    new EmailovaPromenna('vygenerovano', 'kdy se report vygeneroval', '2026-10-16_12-30-00'),
                ],
            ),
            new EmailovaSablonaDefinice(
                kod: 'bfgr_report_chyba',
                skupina: self::SKUPINA_REPORTY,
                nazev: 'BFGR report se nepodařilo vygenerovat',
                kdyOdchazi: 'Když se vyžádaný BFGR report nepodaří vygenerovat.',
                prijemce: 'kdo report vyžádal',
                format: GcMail::FORMAT_HTML,
                promenne: [
                    new EmailovaPromenna('jmeno', 'jméno toho, kdo report vyžádal', 'Jana Nováková'),
                    new EmailovaPromenna('chyba', 'co se nepovedlo', 'Report nebyl vygenerován nebo je prázdný'),
                ],
            ),
        ];
    }

    private static function nazevAktivity(): EmailovaPromenna
    {
        return new EmailovaPromenna(
            nazev: 'aktivita',
            popis: 'název aktivity',
            ukazka: 'Dračí doupě: Hrob krále trpaslíků',
            vPredmetu: true,
        );
    }

    private static function koncovka(bool $vPredmetu = true, bool $volitelna = false): EmailovaPromenna
    {
        return new EmailovaPromenna(
            nazev: 'a',
            popis: 'koncovka podle pohlaví příjemce: u ženy „a“, jinak nic (např. „zapomněl%a%“)',
            ukazka: 'a',
            vPredmetu: $vPredmetu,
            volitelna: $volitelna,
        );
    }

    private static function kdyBudeOdhlaseni(): EmailovaPromenna
    {
        return new EmailovaPromenna(
            nazev: 'kdy',
            popis: 'jak brzy bude hromadné odhlášení, s velkým písmenem na začátku („Zítra“, „Za hodinu“)',
            ukazka: 'Zítra',
            vPredmetu: true,
        );
    }

    private static function gcSkoncil(): EmailovaPromenna
    {
        return new EmailovaPromenna(
            nazev: 'gc_skoncil',
            popis: 'kdy skončil GameCon',
            ukazka: '19.07.2026 21:00',
        );
    }
}
