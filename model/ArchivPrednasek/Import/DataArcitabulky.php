<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek\Import;

/**
 * What the import of „Arcitabulka přednášek“ does with the data, approved by the person who ordered the archive
 * (Trello 1508). The spreadsheet itself stays unchanged in migrace/pomocne/archiv-prednasek, so anyone can compare.
 *
 * Nothing is looked up while the migration runs: lectures are paired by fixed activity ids and speakers linked
 * by fixed user ids, each checked against the year and title (or the hash of the login) seen in the copy of the
 * production database on 2026-11-10. A mismatch leaves the lecture unpaired or the speaker unlinked and is logged.
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
     * Unified name of a speaker => [user id, sha256 of the login]. Found through the organisers of the activity
     * the lecture matches. Groups and companies (Czech Games Edition, Deskofobie, Altar, Majstri svetov, GameCon, …)
     * have no user, even where they organised the activity from an account of their own (záměr).
     *
     * Only the hash of the login: the repository is public and some logins are e-mail addresses.
     *
     * @var array<string, array{0: int, 1: string}>
     */
    public const array UZIVATELE = [
        'Adam Španěl'               => [4357, 'e2319891e0a70e62a3cf6d6448a7b769d4601a0abf1d3df618dac118652f68f7'],
        'Aikin'                     => [1173, '776aa2aaa9b7e6fc6f658df4a84badac4f194beef06ac068c90b15a75d2a8777'],
        'Alnag'                     => [1357, 'c37e2798bcae38cda67a64a1855b4fa14b80af88b748707b1d5545f17912f72d'],
        'Alwin'                     => [1253, '9be79701b23a43b70bf68783b8e7c33d7ed6af0b4241da0022db50c7fb64cb0c'],
        'Apophis'                   => [929, '10c43b72865ed396af36ce330d74e217ba157481a14909c7b3492d3a34c15ad2'],
        'Aquila'                    => [843, '445bdff27baa04fed3ab8a669dc8c5aa625c480447b522829ab43a101dd82f7c'],
        'Archimedesfort1'           => [4863, 'a778e8761cf2430e094d97f37977545965b114560b3b8e644cfec7fa5d5a2736'],
        'Babcom'                    => [4843, '7daf001727b7f0c3eb4cadd587444cce55db9850358e7a0df34025ab8dbae6f7'],
        'Bajo'                      => [1771, 'ece6db80e37700b71f538a0d7071ae1163c2c0753b6484b5dc05ac0ab8f1e9b1'],
        'Barča'                     => [1011, 'f134e7c9a37bd486e0496610da7c266a254abd452f07f55caea9e739c8e5f323'],
        'Bobaque'                   => [76, '387132066b4e5872dfc1ccdebbe074ae813d7c91e824162b11c68cdf917b326b'],
        'Borgash'                   => [4898, '02261e2d9fd0f0d75a22aadc76abf2243df0691d2c6a6fd68d77e27bfe2c64f5'],
        'Brony'                     => [618, 'b95fa02d6a80f4793732ce7f44b6b9705ab9a01b227f920969017f1a28c9654c'],
        'Bunny'                     => [2800, '03dac94651a68d28f655022e51379654976ad275481fa006ffd0eff9e9e2b08a'],
        'Captain66'                 => [126, 'd09f4b8c4e54d4fbcb7a84d3f550a199d8af35bcdeac61c575eb9ccd76ea4b3c'],
        'Cemi'                      => [1112, 'deef6d1c87bcb4c47756318750188f3fb3d75c6979aa0ab26430fc264976f54f'],
        'Conlai'                    => [330, '78751efc1f728cda442984f49da54ae6914143c6012d987b04b0de0e6d74bfae'],
        'Dalcor'                    => [228, '8cdac2e2e4a9eae9d2e0e3219b44e877687133ad4e9428ceb641f62350f2712a'],
        'Daniel Vázquez'            => [4828, '59bac5b715b4c093424d89713ef1da1a5e90a4a6910a69df4e193f266f331850'],
        'Darien'                    => [326, '58f8b23cffde030ad035183009716938e4410225f7b6d27549fa66f67094e6b8'],
        'Darion'                    => [2413, 'cbb82a467bd93f025b3bf2ca48caeaab5c56f3164b6657e39bb0a13051ff749a'],
        'deshi'                     => [84, '0385524e5ddcff3d30cd64bff51fe829a62c6d590692206f484fcf70ab0a91e7'],
        'Destil'                    => [1920, 'a09c9f6c04d3cabe48aafbc42b3ea516a089c21bed1a009bfb1c292208e49885'],
        'dis'                       => [2359, '12901dece9ce1f866e4a1dbd924df1dcb9fbbad9a728f0fa4fa26e76155d6899'],
        'Dodo'                      => [70, 'b2957fcf2ffbf9b8f0d29bd64452dbbd57b55d677f978e9fc39f898ea7bdbdb2'],
        'Drirr'                     => [1021, '55484713aa64b27a5c5fb37b5a51559acaddb3635dd4894ee1f7ba7277191d88'],
        'Ecthelion1'                => [111, '56743208cf83738d6ba1fad1789bb413904d163cea0ffa26b469bd19442a442f'],
        'Eliška Applová'            => [1139, 'f604096b6c12624e268dfa17ab32a55f86124982f43156b0655520b5a3ef6636'],
        'Erric'                     => [52, 'e4ead87d9a327c39cb927c1ad4374bdd2cdc0bd7a5d260fd4716e9bb5501fef7'],
        'Estanor'                   => [1362, '158dc60ebe42c548a6be37887e8c229aa6af7232fe73a17c4a4d6742d029deea'],
        'Folcwine'                  => [371, 'bb332fe0c5abc379aadf04ebbfbdf04fb87a83876342fb13fdd79f80331d5397'],
        'Gábina Houšková'           => [2047, '04b5457960b7044bb431e5c08b5c0fed666c72bdc2c595f9f806479355d053ce'],
        'Gergon'                    => [1753, '9107b44f2f19380a4fb30ad5f026900e4f965d02b6142b9cc574c2053364d6d3'],
        'Guff'                      => [236, 'a754d6c8f15a46ad692f96cb668bfd774cf19e418f41af892c460b117d4e2fbb'],
        'Holodoktor'                => [2229, 'f406fb4eda7631f7bebeb82bcfaa2c8c25911fc7eea19b3ad6a5f41230427b5d'],
        'Hraničář'                  => [1268, 'dd8c7da378bd49f4a5e238c4c9325ba378dff603193d4ab1b8387024e06a5876'],
        'Hyperion'                  => [2463, '58f9f0664267d00a780f55682359ce18ca143e508abbe3f1ef562f32a19b4f7a'],
        'Charles007'                => [1460, '96ca932f98133c05885b69f5c4e4c09c566ef71cb447ed506713d3342160c61d'],
        'Chiisai'                   => [927, '7c422d24f8f16c9c038d87b97f19aa9f175488addef8c613d7e3e84c510c5e61'],
        'Chyba'                     => [5949, 'b622e8d6da0490e1991a4b0328998e557143cc30a56eea7c2ddd106639eb9a1f'],
        'irook'                     => [2661, '319e092f42dcf982b96f6cb97618321b990f83dd03be531fb3afbb899486a959'],
        'Ivo'                       => [1655, '7a332bb8c0311c5e12b5a0b0e30780a035d384a7c82aea70b96c2027bf59a0ac'],
        'Jerry!'                    => [88, 'bf098afb6696e7278e15c410221f1b5a526dc28f396310c403b7d43b449cbebe'],
        'Jerson'                    => [4277, '92da4b00c89b07d5bf04251521321eb292aa32b8b41c683cf4108b4d60265554'],
        'Jezevec_CoM'               => [103, 'd0f7d1cf5a5272b91632e3bd5b08350a8406f790c06b54214264fa08149822f3'],
        'JirkaJ'                    => [1037, '1942d350d8608a01a06538febdb414c5d3181f43764e260ae7528d988c0ab868'],
        'Jiří Mikoláš'              => [460, '9cf8ad70c7a3f4ba9ee3a2ff54487c32fcdbb47f6108a848e76a7b4d8469f5c2'],
        'Johnjarik'                 => [51, '1783e29d9576ab0c23dff9264b54789b350014d0bf6649efebc0d41cf0391430'],
        'Julianne'                  => [2292, '34319f1a4605d720d39c5db165524e8747a969ad04ac331d5af3447d7dcab427'],
        'Kaerpi'                    => [281, '488cadd22837891c8b0ccdece351706e3645be61f72590e2d97998ea6a1fb10d'],
        'karolko'                   => [869, '3de95dd6d41bb9fbe519574cc49502e050b958c27a3be64b51b7a2a66f733a0a'],
        'Karpianus'                 => [1316, 'a09df2926d64c5b33d0dc1e886da468abab810b6e678fc7b8f4bcf743ed868b0'],
        'KREON'                     => [1450, '777853e4c3d547d3643cae8cffea03383fc35058b677622d1ad309304a822157'],
        'Kristýna Sněgonová'        => [4983, '3408da9bdf9e81dbdaaf602dc7991408815907d110f98debdc701c54a1df59c5'],
        'Kuny'                      => [2388, '1fb321c521f6feb2f5a1f2984e4bfc3ebe1b6a5491f6bb426d874cf8a96b71e1'],
        'Kybersova'                 => [4848, '29fd80d19ed070092fa1586eb5e9ff597a98c76c4d3b49b22aaad96d25982df1'],
        'Lečimír'                   => [61, 'f68d94eabe527dd1f6ec596f91654b16e7cc253a36afdb0259db48e6564c2411'],
        'Leoš'                      => [1687, '4c2259ebb8bdde3081fcfaddff3f26faeac25f9191257d05dc278ff8bbf0ffca'],
        'Lev'                       => [90, '271237599b1662b3e5f53b62a098fb0b2cb925caeb49bdd44ca8e992d75e6899'],
        'Lischai'                   => [320, '1754df637cb63511d1d69e3f0a6cc2f7172b89ad3ba585a18eb444bad5baa57f'],
        'Lorelei'                   => [338, '66118099af2520535a1c6fbcf9f80f7479ccd4403dc972fee57d3af06448ab00'],
        'Lukáš Vavrečka'            => [2293, '4ed0c8cb8f981a680b34da0f12af4b0fd1c4928d92fb6bfc3340f1f87304d7bd'],
        'mackan'                    => [120, '2663c5015e9695e4beb4ce86293fc3c7e19d9e286331b88a15826fe8ccd0fbcb'],
        'Markus'                    => [104, '24d9c7b19834aa278ce9609a00d156f61bd83184c2634d1c2b1d6aaa9c235b3a'],
        'Marky'                     => [1455, 'deadec8d95f2146e99a1ffd2ce69fcaa8a2b0ab9f30f96ee26653c2a3d8d0062'],
        'MarkyParky'                => [1029, 'f786ddcb93a532240a55cba7429aba3bd3a8a117386b9e58656b3df6941ac899'],
        'Maro'                      => [2310, 'b7f3b237ff3231ddcb4f983b576835b117a9ca7957137a29858a988273510781'],
        'Marťa'                     => [146, '4f573d1dbdeb9f4f35966f89af21dcc0c579383180b36665ce0c6f96fb6f9c71'],
        'Martin Bečan'              => [6499, '656ae7bc1b5711d56a98b260ba0727a9d972a43e1bc3888245c541fa26219646'],
        'Martin Holouš'             => [2641, '929a92114156023c866a17470f745be72a74a214b38616838b0008e37f6265c1'],
        'Matulix'                   => [4900, '56009cdd3087afdabfd80c3c1492695d94d55a3c43f05ac7d721b0962514a98a'],
        'Maty'                      => [2960, 'e1259e704d28189c5ac708eba6da537a26995d6cc187736b33bfbd363bcfb9c6'],
        'Michael Bronec'            => [2391, '544aa219276ed7ce01d79159e9c3a7d2f2793dc87b1bbfdd38177e2977e30a90'],
        'Michal Krupička'           => [6439, '52e0f0094b0d5247ace9a7bbb388bf8d0e6ca49f7c1fe2b796bbc41a486bed42'],
        'Mrkew'                     => [782, 'bd18cc9e72b1c28ddb087e6b9e8981eab07c8ec49f4f3aa66b4d7cd9a80fef75'],
        'Nera'                      => [2126, '92f7bbc42380be3547bdfebc622581ae22be7180e3dfc4a8a3648ba55e20eb43'],
        'Noeh'                      => [497, '85cbd1d4cc10b69da3aa08d8c5416611d9af592a6737040df5b7de3aab11db18'],
        'Nord'                      => [4849, '85fb49e051915a77ef6d27b52ce52eb5c4f81d46e18e1526eb59ddb150289ea7'],
        'Peekay'                    => [98, 'f0ecfda873e23c55bb42dd205501fbaf68d7e9cfa52380a8acf575634f33e310'],
        'Petra Slováková'           => [2295, 'a20f383c773849a0ee45a91b08de4591ec4da93b4c6d1dd59e65563ad0c824d7'],
        'petruschka'                => [2411, '9033e6c327e81f1553d673e7742f0996f71e27a2d3c39fef36ec6f3f49f2547b'],
        'Pokča'                     => [6425, 'd573fc2d6365e354ba08e1ae80bafbb0318de5623ed01f3ad1b881c452758e34'],
        'Přemysl Krejčík'           => [2291, 'b2ed93a7793e8c199c0bbb4305f9064eddc9bb82df28282e9b328e2247724b4c'],
        'Radek Richtr'              => [2555, 'f8ba597956fc954bd2bf72ac1c05a86e1c3adc8f9876d7dd602162bb15a4d3e0'],
        'Rauksul'                   => [2111, '58db29e0401c3f67080187826c0f3bf98e313fa4a42dcb1d78d555769c77f887'],
        'Ricarderon'                => [2410, 'e888996da3f27bfb7f742f84f7301035b9271948fe7fa0370a01fadc15ac5190'],
        'Rinvit'                    => [979, 'be1c974465d619bd5fbbc2bde3e9fa6c4d763b0c7f52860bd0e98f6fc8967155'],
        'RomiK'                     => [57, 'c3e02065a5bce419e53d49a9c25f237c93ea854ffc93d693cc715b2533605487'],
        'Sakim'                     => [948, '04aebe863eb2e693000bb77e963ca9a78b8cbd5a9d36a1a334ca50f189417a61'],
        'Scratt'                    => [1591, '1fed6cd4d2b40f99c2a460fe79fb387831fc22a7f9ad2be7527fdcb809adebf0'],
        'Seth'                      => [314, 'f8de6de56aa126973c37b350221ef4fbd2bdb58b889b48b13363a21d2bea5206'],
        'Shadowulf'                 => [1409, '6117f25efcb5b5ea6b5b26e017d3265eeefa4cf4570af4f642eccb9fa664ed1a'],
        'Sikar'                     => [2349, '1e2cac6b180476dc4d17c275cd6638d87e4b3c7d7fbf34c06b45b08c7afe48ef'],
        'sirien'                    => [102, '75a35c53fdee8ace76fe56aa4e020b8811672a8011de877ba9593215a52e0b03'],
        'Sparkle'                   => [991, 'abac3d1ed3920c65b77914e65fb98d58f0b64197db993b673f09227c6ec88a69'],
        'Thaddeus'                  => [85, '0b259927bce6b865083f3c9d3922e44ec519e1fa644f589bafe5af09befb123c'],
        'Thanatophagous'            => [5420, '4a5ab59b433e07334ad32dd90049796e81235f931b776d8956cc3fe382c8e4d4'],
        'Thorvald'                  => [5421, '1e3ada15ecad88b706f636a49248e5bace736de9f4a5ab9aa7a3ffab6c596962'],
        'Tirus'                     => [1028, '505c1293d5c0caed0fd840b863f5c81cf24f50cba5fc682b74ea001877a171b3'],
        'Tomáš Houška'              => [2662, '20ee0008483ffd25e73f29903098b935f714ea3740fa6cd97283443a33c83a8d'],
        'Toren'                     => [3023, '2fb7c3e0d7025a373b2a63a17515fdd2714c605684eb744c4c9a5b9fbfd97e3c'],
        'Totus'                     => [731, 'aa7e847d9ddee3f7268380eae26809d7ece2fd87f06dfdd1869c6e2abcc1c8dd'],
        'ubik'                      => [976, 'b3c4ab97238044d3dd0d86c051e79dac9f343c4b074f77ca48019f3a3c2a854b'],
        'Václav Pecháček'           => [6071, 'fcfac73f0d79f7525a36debd64df4d5319dddc45c0aeb6826134a43418831816'],
        'Vinc'                      => [1449, 'f526231bb5dc7dc3a5431af5022fcac3f4f9e220141e7881a13f71c1de209d1a'],
        'Vojtěch (Hraní u nádraží)' => [4935, 'cadb84b4a54065eeda35633425024c8312da2642be66a3bb1df8f79caec0bb85'],
        'von Pecka'                 => [757, '8435b5e14067c1660b5339c2f03fdbf4548c6a045a5ada03a07254357de87f7d'],
        'Walker'                    => [2282, 'f4034e7eb42b1a87bcfddd40dd0aa64c251bb4d5f2c8b0679f0988cffb2d4e54'],
        'Yetti'                     => [1036, '1ff48bd5f0068f936f67027820e31831eb7cda1eb8501bbead6161387344db44'],
        'York'                      => [2740, '777e88b51092d314e2b6b9e523fc4dc2f3281c52df171e5a3343ba4bbae44c5b'],
        'Yuffie'                    => [953, '6390aa44e261757c7e3e3a62a293895e4f7b07b1b0ecf551c95e797821f58c36'],
        'Zašek'                     => [6452, 'c044651bccb994a99ee8a4e3708739eec6e53dd5cbbc7940ab5b9bb888f1ad4f'],
        'Zdeněk Jechort'            => [5406, '222b700876a1bf8c0fbd8f79f5221cb7306c69a3042e5d66e8f97b68d988b319'],
        'Zerg (Broodlord)'          => [390, '8b4f14bb7c2ccd2366a474e2366297326e5eea102f4d719ad904417e47727773'],
        'Zizi'                      => [1451, '02b31e234e67fd1a1847e683bc03bfb6f354d04990745653439eb69adfb45ae5'],
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
