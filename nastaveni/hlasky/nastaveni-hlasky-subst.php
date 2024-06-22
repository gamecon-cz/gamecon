<?php

use Gamecon\Hlaska\Hlaska;

return [
    'odhlasilPlatil'                        => 'Uživatel %1 (ID %2) %3 z GameConu, ale v aktuálním roce (%4) si poslal %5 Kč. Bude vhodné to prověřit popř. smazat platby z připsaných a dát do zůstatku v seznamu uživatelů, aby mu peníze nepropadly',
    'odhlasilMelUbytovani'                  => 'Uživatel %1 (ID %2) %3 z GameConu a v aktuálním roce (%4) měl ubytování ve dnech %5. Uvolnilo se tak místo.',
    'uvolneneMisto'                         => 'Na aktivitě %1, která se koná v %2, se uvolnilo místo. Tento e-mail dostáváš, protože máš nastavené sledování uvedené aktivity. Přihlaš se na aktivitu přes <a href="https://gamecon.cz/program">program</a> (pospěš si, ať ti místo nezabere někdo jiný).',
    'chybaClenaTymu'                        => 'Nepodařilo se přihlásit tým. Při přihlašování uživatele %1 (id %2) se u něj objevila chyba: %3',
    'zapomenuteHeslo'                       =>
        'Ahoj,

dostali jsme žádost o vygenerování nového hesla na Gamecon.cz. Tvoje přihlašovací jméno je stejné jako e-mail (%1), tvoje nové heslo je %2. Heslo si prosím po přihlášení změň.

S pozdravem Tým organizátorů GameConu',
    'odhlaseniZGc'                          => 'Odhlášení z GameConu ' . ROCNIK . ' proběhlo úspěšně',
    'prihlaseniNaGc'                        => 'Přihlášení na GameCon ' . ROCNIK . ' proběhlo úspěšně',
    'prihlaseniTeamMail'                    =>
        'Ahoj,

v rámci GameConu tě %1 přihlásil{a} na aktivitu %2, která se koná %3. Pokud s přihlášením nepočítáš, nebo na aktivitu nemůžeš, dohodni se prosím s tím, kdo tě přihlásil, případně se odhlas na <a href="https://gamecon.cz">webu gameconu</a>.

Pokud člověka, který tě přihlásil, neznáš, kontaktuj nás prosím na <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>.',
    'kapacitaMaxUpo'                        => 'Z ubytovací kapacity typu %1 je naplněno %2 míst z maxima %3 míst.',
    'rychloregMail'                         =>
        'Ahoj,

děkujeme, že se letos účastníš GameConu! Kliknutím na odkaz níže potvrdíš registraci na web, kde si nastavíš přezdívku a heslo tak, ať můžeš používat web a přijet třeba i příští rok. V případě pozdější registrace na web by bylo nutné nechat si vygenerovat heslo znovu.

<a href="https://gamecon.cz/potvrzeni-registrace/%2">https://gamecon.cz/potvrzeni-registrace/%2</a>',
    Hlaska::NEDOSTAVENI_SE_NA_AKTIVITU_MAIL =>
        'Ahoj,

vypadá to, že aktivita %1 proběhla bez tebe!

Chápeme, že může nastat situace, kvůli které se nemůžeš, nebo nechceš aktivity zúčastnit, pro příště tě ale moc prosíme

VŽDY SE Z AKTIVITY ODHLAS.

Žádáme tě o to z toho důvodu, aby na tebe vypravěč a ostatní účastníci nemuseli čekat a zjišťovat, jestli dorazíš. A taky to značně urychlí hledání náhradníka. Pokud se odhlásíš dříve než %2 před začátkem aktivity, vrátíme ti za ni všechny peníze. Pokud později, tak %3 %.

Jedná se o omyl? Někde v systému pravděpodobně nastala chyba, za kterou se moc omlouváme. Dej nám o ní prosím vědět, abychom to opravili a znovu tě zbytečně neděsili.

Děkujeme za spolupráci!

Tvůj,
Organizační tým GC',
];
