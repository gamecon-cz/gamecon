<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek\Import;

/**
 * What the import of „Arcitabulka přednášek“ does with the data, approved by the person who ordered the archive
 * (Trello 1508). The spreadsheet itself stays unchanged in migrace/pomocne/archiv-prednasek, so anyone can compare.
 *
 * Nothing is looked up while the migration runs: lectures are paired by fixed activity ids and speakers linked
 * by fixed user ids, each checked against the year and title (or the login) seen in the copy of the production
 * database on 2026-11-10. A mismatch leaves the lecture unpaired or the speaker unlinked and is logged.
 *
 * Lectures up to 2017 are not paired (záměr: the old years are not compatible enough), but their speakers are
 * linked through the organisers of the old activity, the accounts are the same in every year.
 */
final class DataArcitabulky
{
    /**
     * Lower-case keyword => unified one. Where the program has the same tag, its spelling wins, so that a lecture
     * does not show two forms of the same tag. Keywords not listed keep the spelling met first.
     */
    public const array KLICOVA_SLOVA = [
        // typos and variants
        'horror'             => 'Horor',
        'horor'              => 'Horor',
        'seting'             => 'Setting',
        'larpy'              => 'Larp',
        'larp'               => 'Larp',
        'tvorby'             => 'Tvorba',
        'wg'                 => 'Wargaming',
        'zapovězené země'    => 'Zapovězené země',
        'lone wolf rpg'      => 'Lone Wolf',
        'call of cthullu 7e' => 'Call of Cthulhu 7e',
        // the same meaning, written like the tag of the program
        'diskuze'   => 'Debata',
        'panelovka' => 'Panelová diskuse',
        'dračák'    => 'Dračí doupě',
        'svět'      => 'Setting',
        'jrrt'      => 'Pán prstenů',
    ];

    /**
     * Name as written in the spreadsheet => unified one, one person written in several ways.
     * Marky and MarkyParky stay apart (two accounts), Charles and Charles007 too (nothing proves it is one person).
     */
    public const array JMENA_PREDNASEJICICH = [
        'Jerry'       => 'Jerry!',
        'Mackan'      => 'mackan',
        'Jezevec'     => 'Jezevec_CoM',
        'Ecthelion^2' => 'Ecthelion1',
        'bobaque'     => 'Bobaque',
        'Torren'      => 'Toren',
    ];

    /**
     * Unified name of a group => its members, added after the group to each of its lectures so that their profiles
     * are linked (záměr: Majstri svetov are Babcom and Thanatophagous, who organised all three activities).
     */
    public const array CLENOVE_SKUPIN = [
        'Majstri svetov' => ['Babcom', 'Thanatophagous'],
    ];

    /**
     * Title as written in the spreadsheet (trimmed) => corrected one, typos that the program has right.
     */
    public const array OPRAVY_NAZVU = [
        'Hecrawl: jde vůbec udělat dobré dobrodružství pod širým nebem?' => 'Hexcrawl: jde vůbec udělat dobré dobrodružství pod širým nebem?',
        'Úskalí komedionálního larpu'                                    => 'Úskalí komediálního larpu',
        'Jak se nesklamat z tvorby'                                      => 'Jak se nezklamat z tvorby',
    ];

    /**
     * Unified name of a speaker => [user id, login]. Found through the organisers of the activity the lecture
     * matches. Groups and companies (Czech Games Edition, Deskofobie, Altar, Majstri svetov, GameCon, …) have no user,
     * even where they organised the activity from an account of their own (záměr).
     *
     * @var array<string, array{0: int, 1: string}>
     */
    public const array UZIVATELE = [
        'Adam Španěl'               => [4357, 'BoltKey'],
        'Aikin'                     => [1173, 'Aikin'],
        'Alnag'                     => [1357, 'Alnag'],
        'Alwin'                     => [1253, 'Alwin'],
        'Apophis'                   => [929, 'apophis'],
        'Aquila'                    => [843, 'Aquila'],
        'Archimedesfort1'           => [4863, 'Archimedesfort1'],
        'Babcom'                    => [4843, 'Babcom'],
        'Bajo'                      => [1771, 'Bajo'],
        'Barča'                     => [1011, 'Barča'],
        'Bobaque'                   => [76, 'boubaque'],
        'Borgash'                   => [4898, 'Borgash'],
        'Brony'                     => [618, 'Brony'],
        'Bunny'                     => [2800, 'Bunny'],
        'Captain66'                 => [126, 'captain66'],
        'Cemi'                      => [1112, 'Cemi'],
        'Conlai'                    => [330, 'conlai'],
        'Dalcor'                    => [228, 'Dalcor'],
        'Daniel Vázquez'            => [4828, 'Casa de Locos'],
        'Darien'                    => [326, 'Darien'],
        'Darion'                    => [2413, 'Darion'],
        'deshi'                     => [84, 'deshi'],
        'Destil'                    => [1920, 'Destil'],
        'dis'                       => [2359, 'dis'],
        'Dodo'                      => [70, 'Dodo'],
        'Drirr'                     => [1021, 'Drirr'],
        'Ecthelion1'                => [111, 'Ecthelion1'],
        'Eliška Applová'            => [1139, 'MomOfVetřelecAndPredátor'],
        'Erric'                     => [52, 'Erric'],
        'Estanor'                   => [1362, 'Estanor'],
        'Folcwine'                  => [371, 'Folcwine'],
        'Gábina Houšková'           => [2047, 'Gábina'],
        'Gergon'                    => [1753, 'Gergon'],
        'Guff'                      => [236, 'Guff'],
        'Holodoktor'                => [2229, 'Holodoktor'],
        'Hraničář'                  => [1268, 'Hraničář'],
        'Hyperion'                  => [2463, 'Hyperion'],
        'Charles007'                => [1460, 'Charles007'],
        'Chiisai'                   => [927, 'Chiisai'],
        'Chyba'                     => [5949, 'Chyba'],
        'irook'                     => [2661, 'irook'],
        'Ivo'                       => [1655, 'Ivo'],
        'Jerry!'                    => [88, 'Jerry!'],
        'Jerson'                    => [4277, 'Jerson'],
        'Jezevec_CoM'               => [103, 'Jezevec_CoM'],
        'JirkaJ'                    => [1037, 'JirkaJ'],
        'Jiří Mikoláš'              => [460, 'Jiraman'],
        'Johnjarik'                 => [51, 'JohnJarik'],
        'Julianne'                  => [2292, 'Julianne'],
        'Kaerpi'                    => [281, 'Kaerpi'],
        'karolko'                   => [869, 'karolko_true'],
        'Karpianus'                 => [1316, 'Krotitel'],
        'KREON'                     => [1450, 'KREON'],
        'Kristýna Sněgonová'        => [4983, 'Proč je přezdívka povinný údaj?'],
        'Kuny'                      => [2388, 'Kuny'],
        'Kybersova'                 => [4848, 'Kybersova'],
        'Lečimír'                   => [61, 'Lečimír'],
        'Leoš'                      => [1687, 'Leoš'],
        'Lev'                       => [90, 'lev'],
        'Lischai'                   => [320, 'Lischai'],
        'Lorelei'                   => [338, 'Lorelei'],
        'Lukáš Vavrečka'            => [2293, 'Kafkowski'],
        'mackan'                    => [120, 'mackan'],
        'Markus'                    => [104, 'markus'],
        'Marky'                     => [1455, 'Marky'],
        'MarkyParky'                => [1029, 'MarkyParky'],
        'Maro'                      => [2310, 'Maro'],
        'Marťa'                     => [146, 'Marťa'],
        'Martin Bečan'              => [6499, 'Martin Bečan'],
        'Martin Holouš'             => [2641, 'Martin Holouš'],
        'Matulix'                   => [4900, 'Matulix'],
        'Maty'                      => [2960, 'matyas.vesely@gmail.com'],
        'Michael Bronec'            => [2391, 'Barbar'],
        'Michal Krupička'           => [6439, 'Gor'],
        'Mrkew'                     => [782, 'Mrkew'],
        'Nera'                      => [2126, 'Nera'],
        'Noeh'                      => [497, 'Noeh'],
        'Nord'                      => [4849, 'Nord'],
        'Peekay'                    => [98, 'Peekay'],
        'Petra Slováková'           => [2295, 'Petra Slováková'],
        'petruschka'                => [2411, 'petruschka'],
        'Pokča'                     => [6425, 'Pokča'],
        'Přemysl Krejčík'           => [2291, 'sharik'],
        'Radek Richtr'              => [2555, 'radek.rychtr@faid.čvut.cz'],
        'Rauksul'                   => [2111, 'Rauksûl'],
        'Ricarderon'                => [2410, 'Ricarderon'],
        'Rinvit'                    => [979, 'Rinvit'],
        'RomiK'                     => [57, 'RomiK'],
        'Sakim'                     => [948, 'Sakim'],
        'Scratt'                    => [1591, 'Scratt'],
        'Seth'                      => [314, 'Seth'],
        'Shadowulf'                 => [1409, 'Shadowulf'],
        'Sikar'                     => [2349, 'Sikar'],
        'sirien'                    => [102, 'sirien'],
        'Sparkle'                   => [991, 'Sparkle'],
        'Thaddeus'                  => [85, 'Thaddeus'],
        'Thanatophagous'            => [5420, 'Thanatophagous'],
        'Thorvald'                  => [5421, 'Thorvald'],
        'Tirus'                     => [1028, 'Sir_Tirus'],
        'Tomáš Houška'              => [2662, 'Tomian'],
        'Toren'                     => [3023, 'Toren'],
        'Totus'                     => [731, 'Totus'],
        'ubik'                      => [976, 'ubik'],
        'Václav Pecháček'           => [6071, 'Ingie'],
        'Vinc'                      => [1449, 'Vinc'],
        'Vojtěch (Hraní u nádraží)' => [4935, 'Adalbert Earwig'],
        'von Pecka'                 => [757, 'von Pecka'],
        'Walker'                    => [2282, 'Walker'],
        'Yetti'                     => [1036, 'Yetti'],
        'York'                      => [2740, 'York'],
        'Yuffie'                    => [953, 'Yuffie'],
        'Zašek'                     => [6452, 'Zašek'],
        'Zdeněk Jechort'            => [5406, 'Jacen Cage'],
        'Zerg (Broodlord)'          => [390, 'Broodlord'],
        'Zizi'                      => [1451, 'Zizi'],
    ];

    /**
     * Lectures of 2018–2025 paired with a card of the program: [unified title, year, id of the main activity
     * of the card, its title in the program]. The title is compared after NazevPrednasky::uprav().
     *
     * @var list<array{0: string, 1: int, 2: int, 3: string}>
     */
    public const array PAROVANI = [
        ['Digi Jeskyně', 2025, 6720, 'Digi Jeskyně'],
        ['Jak jsem potkal / dělal Dračí doupě', 2025, 6747, 'Jak jsem potkal / dělal Dračí doupě'],
        ['Láska, sex a něžnosti v RPG', 2025, 6189, 'Láska, sex a něžnosti v RPG'],
        ['Mistrovství DrD – Hodnocení družin, postupová kritéria a FAQ', 2025, 6596, 'Mistrovství DrD – Hodnocení družin, postupová kritéria a FAQ'],
        ['Novinky a trendy RPG světa 2025', 2025, 6193, 'Novinky a trendy RPG světa'],
        ['Novinky na české RPG scéně 2025', 2025, 6191, 'Novinky na české RPG scéně'],
        ['One-Shot: Od nápadu po realizaci', 2025, 6190, 'One-Shot: Od nápadu po realizáciu'],
        ['Podcast díra – Z audiopříběhu na herní stůl', 2025, 6689, 'Podcast Díra – Z audiopříběhu na herní stůl'],
        ['RPG kobky nejen pro prolézače', 2025, 6354, 'RPG kobky nejen pro prolézače'],
        ['Základy malování deskovek – BEJSKY', 2025, 6195, 'Základy malování deskovek – BEJSKY'],
        ['Základy malování deskovek – FIGURKY', 2025, 6194, 'Základy malování deskovek – FIGURKY'],
        ['Základy malování deskovek – TERÉNY', 2025, 6196, 'Základy malování deskovek – TERÉNY'],
        ['Zapomeňte na chaotické poznámky', 2025, 6721, 'Zapomeňte na chaotické poznámky'],
        ['Cyberpunk: od Zrcadlovek po Singularitu', 2024, 5413, 'Cyberpunk: od Zrcadlovek po Singularitu'],
        ['Deskové hry v Československu', 2024, 5417, 'Deskové hry v Československu'],
        ['Dragonaer: svět pro jeskyně a draky', 2024, 5416, 'Dragonaer – svět pro Jeskyně a draky'],
        ['Hvězdná pěchota v deskovce se vrací', 2024, 5420, 'Hvězdná Pěchota v Deskovce se vrací!'],
        ['Kampaně vs One-shoty', 2024, 5418, 'Kampaně vs. One-shoty'],
        ['Mistrovství DrD – Hodnocení družin, postupová kritéria, FAQ a … směřování sekce MDrD', 2024, 5466, 'Mistrovství DrD – Hodnocení družin, postupová kritéria, FAQ a … směřování sekce MDrD!'],
        ['Novinky na RPG scéně 2024', 2024, 5415, 'Novinky na RPG scéně'],
        ['Příběh a magie místa', 2024, 5412, 'Příběh a magie místa'],
        ['Roleplay v kulisách pána prstenů', 2024, 5996, 'Roleplay v kulisách Pána prstenů '],
        ['Základy barvení figurek: Tipy pro nováčky.', 2024, 5419, 'Základy barvení figurek. Tipy pro nováčky.'],
        ['Battletech: Rytíři v kráčejících strojích', 2023, 4756, 'BATTLETECH - Rytíři v kráčejících strojích'],
        ['Empír, Medžik, Fejt a Stým. Co uděláme s tím?', 2023, 4762, 'Empír, Medžik, Fejt a Stým. Co uděláme s tím?'],
        ['Hexcrawl: jde vůbec udělat dobré dobrodružství pod širým nebem?', 2023, 4760, 'Hexcrawl: jde vůbec udělat dobré dobrodružství pod širým nebem?'],
        ['Historie deskových her', 2023, 4755, 'Historie deskových her'],
        ['Jak využít AI a ChatGPT v RPG', 2023, 5321, 'Jak využít AI a ChatGPT v RPG?'],
        ['Majstri svetov Live: Prečo hrať naratívne RPG', 2023, 4761, 'Majstri svetov Live: Prečo hrať naratívne RPG?'],
        ['Mistrovství DrD – Hodnocení družin, postupová kritéria a FAQ', 2023, 4763, 'Mistrovství DrD - Hodnocení družin, postupová kritéria a FAQ'],
        ['Novinky z nakladatelství Casa de Locos', 2023, 4753, 'Novinky z nakladatelství Casa de Locos'],
        ['Smysl života v pravidlech DnD', 2023, 4759, 'Smysl života v pravidlech DnD?'],
        ['Tvorba světa pro RPG', 2023, 5334, 'Tvorba světa pro RPG'],
        ['Asterion: Krajiny za obzorem 2', 2022, 4288, 'Asterion: Krajiny za obzorem 2'],
        ['Duna online: Od legendární webové hry k larpu', 2022, 4057, 'Duna online: od legendární webové hry k larpu'],
        ['Gamemaster – Úskalí dobře odvedeného dobrodružství', 2022, 4427, 'Gamemaster - úskalí a svízel dobře odvedeného dobrodružství'],
        ['Historie Duny', 2022, 4375, 'Historie Duny'],
        ['Jak na evropský raný středověk v RPG', 2022, 4506, 'Jak na evropský raný středověk v RPG'],
        ['Jak vzniká dračák', 2022, 4289, 'Jak vzniká Dračák'],
        ['Křest pravidel Jeskyně a draci', 2022, 4398, 'Křest pravidel Jeskyně a draci'],
        ['Mistrovství DrD – Hodnocení družin a postupová kritéria', 2022, 4287, 'Mistrovství DrD - Hodnocení družin a postupová kritéria '],
        ['Novinky na RPG scéně 2022', 2022, 4397, 'Novinky na RPG scéně'],
        ['Psychologie příběhů: Od motivace ke konfliktu', 2022, 4374, 'Psychologie příběhů: Od motivace ke konfliktu'],
        ['Speciální metafyzika Dračího doupěte 1.6 E', 2022, 4505, 'Speciální metafyzika Dračího doupě verze 1.6E'],
        ['Strangers and Dragons', 2022, 4290, 'Strangers And Dragons'],
        ['Warhammer 40k', 2022, 4378, 'Warhammer 40k - Úvod do temného vesmíru budoucnosti'],
        ['Živit se tímhle', 2022, 4376, 'Živit se tímhle'],
        ['Cesty časem – Od příběhu k produkci', 2021, 3913, 'Od příběhu k produkci'],
        ['Hororová atmosféra v RPG', 2021, 3943, 'Horrorová atmosféra v RPG'],
        ['Jak se nezklamat z tvorby', 2021, 3912, 'Jak se nezklamat z tvorby'],
        ['Jak se tvoří deskovka', 2021, 3950, 'Jak se tvoří deskovka'],
        ['Novinky na české RPG scéně 2021', 2021, 3941, 'Novinky na české RPG scéně'],
        ['Ty larpy nejsou pro boomery', 2021, 3877, 'Tyhle larpy nejsou pro boomery'],
        ['Úskalí komediálního larpu', 2021, 3911, 'Úskalí komediálního larpu - panelová diskuze'],
        ['Filmové fantasy propadáky a kde je problém?', 2019, 3220, 'Filmové fantasy propadáky a kde je problém?'],
        ['Inspiruj se mýty', 2019, 3441, 'Inspiruj se mýty'],
        ['Jak mě kreslení Hustýho nářezu málem přivedlo k šílenství', 2019, 3218, 'Jak mě kreslení Hustýho nářezu málem přivedlo k šílenství'],
        ['Kdo je proboha ten pirát?', 2019, 3289, 'Kdo je proboha ten pirát?'],
        ['Piráti z Baltiku: Truhla mrtvých mužů aneb Prokletí mokrých onucí', 2019, 3219, 'Piráti z Baltiku: Truhla mrtvých mužů aneb Prokletí mokrých onucí'],
        ['Pirátství ve hrách na hrdiny', 2019, 3216, 'Pirátství ve hrách na hrdiny'],
        ['Příběhy slavných pirátů', 2019, 3217, 'Příběhy slavných pirátů'],
        ['Slovanské mýty', 2019, 3442, 'Slovanské mýty'],
        ['Tauril pokračuje', 2019, 3285, 'Tauril pokračuje'],
        ['Asterion žije!', 2018, 2736, 'Asterion žije!'],
        ['Deset rad pro začínající autory deskových her', 2018, 2719, 'Deset rad pro začínající autory deskových her'],
        ['Deskofobie live!', 2018, 2720, 'Deskfobie live!'],
        ['Jak si dát old school dungeon k snídani', 2018, 2734, 'Jak si dát old school dungeon k snídani'],
        ['Máme retroRPGčka a nebojíme se je použít', 2018, 2733, 'Máme retro RPGčka a nebojíme se je použít!'],
        ['Neviditelná kniha', 2018, 2679, 'Neviditelná kniha'],
        ['Renesance gamebooků', 2018, 2689, 'Renesance gamebooků'],
        ['Tvorba světa vzhůru nohama', 2018, 2687, 'Tvorba světa vzhůru nohama'],
    ];

    /**
     * Lectures of the program missing from the spreadsheet, added on request: [title, year, id of the main activity,
     * its title in the program, paired?, speakers]. Up to 2017 they are not paired, the id only says where they come from.
     * The speakers are the organisers, by the name they already have in the archive, or by their name on the web.
     *
     * @var list<array{0: string, 1: int, 2: int, 3: string, 4: bool, 5: list<string>}>
     */
    public const array Z_PROGRAMU = [
        ['Balení na conech', 2009, 19, 'Balení na conech', false, ['Jezevec_CoM']],
        ['10 dôvodov, prečo jazdiť na jeepe', 2010, 260, '10 dôvodov, prečo jazdiť na jeepe', false, []],
        ['Hranice příběhů, hranice vědy', 2010, 240, 'Hranice příběhů, hranice vědy', false, []],
        ['Jak přednášet jako rock star?', 2010, 236, 'Jak přednášet jako rock star?', false, ['Lorelei']],
        ['Jak si na večírku povídat s děvčaty', 2010, 226, 'Jak si na večírku povídat s děvčaty', false, ['Lischai']],
        ['Realita je hra', 2010, 252, 'Realita je hra', false, []],
        ['Tajemství ženské linie', 2010, 303, 'Tajemství ženské linie', false, []],
        ['Zasaď strom, postav dům', 2010, 230, 'Zasaď strom, postav dům', false, ['Jezevec_CoM']],
        ['Prášky na psaní', 2011, 429, 'Prášky na psaní', false, []],
        ['Jak psát a nenapsat blábol', 2012, 493, 'Jak psát a nenapsat blábol', false, ['Marťa']],
        ['Argo – novinky', 2017, 2406, 'Argo - novinky', false, ['Ricarderon', 'Hyperion']],
        ['Bašta fantastiky otvírá brány', 2017, 2081, 'Bašta fantastiky otvírá brány', false, ['Leoš']],
        ['Ben Aaronovitch osobně!', 2017, 2077, 'Ben Aaronovitch osobně!', false, ['Ricarderon']],
        ['Cena Karla Čapka včera, dnes a zítra', 2017, 2295, 'Cena Karla Čapka včera, dnes a zítra', false, ['Aquila']],
        ['Co ulétlo J. K. Rowlingové na koštěti Harryho Pottera', 2017, 2060, 'Co ulétlo J. K. Rowlingové na koštěti Harryho Pottera', false, ['Marťa']],
        ['Československá umělá inteligence', 2017, 2064, 'Československá umělá inteligence', false, ['Holodoktor']],
        ['Člověk 2.0', 2017, 2076, 'Člověk 2.0', false, ['Julianne']],
        ['Fantastické knihy roku 2017', 2017, 2069, 'Fantastické knihy roku 2017', false, ['Hyperion']],
        ['Historie pornografie', 2017, 2061, 'Historie pornografie', false, ['Lečimír']],
        ['Humanoidní Terminátoři, aneb od T70 po T nekonečno', 2017, 2063, 'Humanoidní Terminátoři, aneb od T70 po T nekonečno', false, ['Holodoktor']],
        ['Jak napsat bestovní celer a dostat knížku na trh', 2017, 2068, 'Jak napsat bestovní celer a dostat knížku na trh', false, ['Leoš']],
        ['Je libo steampunk?', 2017, 2234, 'Je libo steampunk?', false, ['Petra Slováková', 'petruschka']],
        ['JFK vs. Kladivo na čaroděje', 2017, 2412, 'JFK vs. Kladivo na čaroděje', false, ['Walker', 'Darion']],
        ['Komiksy, kam se podíváš', 2017, 2067, 'Komiksy, kam se podíváš', false, []],
        ['Křest gamebooku Rytíř temného slunce', 2017, 2400, 'Křest gamebooku Rytíř temného slunce', false, ['Nera']],
        ['Kyberpunk – jak ovlivnil celý svět', 2017, 2235, 'Kyberpunk - jak ovlivnil celý svět', false, ['Walker']],
        ['Magnólie', 2017, 2241, 'Magnólie', false, ['Nera']],
        ['Mytago se představuje', 2017, 2242, 'Mytago se představuje', false, []],
        ['Na vodách Lehkého fantazijna', 2017, 2079, 'Na vodách Lehkého fantazijna', false, ['dis']],
        ['Napsat knihu v týmu... za 72 hodin?', 2017, 2236, 'Napsat knihu v týmu... za 72 hodin?', false, ['Přemysl Krejčík', 'Lukáš Vavrečka']],
        ['Někdo to rád studené', 2017, 2233, 'Někdo to rád studené', false, ['Leoš']],
        ['O legendách po Legendách', 2017, 2180, 'O legendách po Legendách', false, ['GameCon']],
        ['Pokec s robotem', 2017, 2078, 'Pokec s robotem', false, ['dis']],
        ['Proč je dobré míti Lovecrafta', 2017, 2065, 'Proč je dobré míti Lovecrafta', false, ['Maro']],
        ['The Europe: Originální tabletový storix', 2017, 2404, 'The Europe: Originální tabletový storix', false, ['Kuny']],
        ['Udusit se pavoučími tělíčky: Techniky hraní hororových RPG', 2017, 2075, 'Udusit se pavoučími tělíčky: Techniky hraní hororových RPG', false, ['Lischai']],
        ['UH – umělá hloupost stokrát jinak', 2017, 2071, 'UH - umělá hloupost stokrát jinak', false, ['Sikar']],
        ['UI ve vesmírných programech', 2017, 2066, 'UI ve vesmírných programech', false, []],
        ['Z galaxií do nereálna a ještě dál', 2017, 2232, 'Z galaxií do nereálna a ještě dál', false, ['Walker']],
        ['Zapomenuté seriály', 2017, 2062, 'Zapomenuté seriály', false, ['Lečimír']],
        ['Život a dílo řezníka z Bruntálu', 2017, 2403, 'Život a dílo řezníka z Bruntálu', false, ['Darien', 'Drirr', 'Leoš']],
        ['Organizace turnajů v deskových hrách', 2019, 3294, 'Organizace turnajů v deskových hrách', true, ['JirkaJ']],
    ];
}
