<?php

use Gamecon\Hlaska\Hlaska;

return [
    'odhlasilPlatil'                        => 'Uživatel %1 (ID %2) %3 z GameConu, ale v aktuálním roce (%4) si poslal %5 Kč. Bude vhodné to prověřit popř. smazat platby z připsaných a dát do zůstatku v seznamu uživatelů, aby mu peníze nepropadly',
    'odhlasilMelUbytovani'                  => 'Uživatel %1 (ID %2) %3 z GameConu a v aktuálním roce (%4) měl ubytování ve dnech %5. Uvolnilo se tak místo.',
    'uvolneneMisto'                         => 'Na aktivitě %1, která se koná v %2 se uvolnilo místo. Tento e-mail dostáváš, protože jsi se přihlásil k sledování uvedené aktivity. Přihlaš se na aktivitu přes <a href="https://gamecon.cz/program">program</a> (pokud nebudeš dost rychlý, je možné že místo sebere někdo jiný).',
    'chybaClenaTymu'                        => 'Nepodařilo se přihlásit tým. Při přihlášování uživatele %1 (id %2) se u něj objevila chyba: %3',
    'zapomenuteHeslo'                       =>
        'Ahoj,

nechal{a} sis vygenerovat nové heslo na Gamecon.cz. Tvoje přihlašovací jméno je stejné jako e-mail (%1), tvoje nové heslo je %2. Heslo si prosím po přihlášení změň.

S pozdravem Tým organizátorů GameConu',
    'odhlaseniZGc'                          => 'Odhlásil{a} ses z GameConu ' . ROCNIK,
    'prihlaseniNaGc'                        => 'Přihlásil{a} ses na GameCon ' . ROCNIK,
    'prihlaseniTeamMail'                    =>
        'Ahoj,

v rámci GameConu tě %1 přihlásil{a} na aktivitu %2, která se koná %3. Pokud s přihlášením nepočítáš nebo na aktivitu nemůžeš, dohodni se prosím s tím, kdo tě přihlásil a případně se můžeš odhlásit na <a href="https://gamecon.cz">webu gameconu</a>.

Pokud člověka, který tě přihlásil, neznáš, kontaktuj nás prosím na <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>.',
    'kapacitaMaxUpo'                        => 'Z ubytovací kapacity typu %1 je naplněno %2 míst z maxima %3 míst.',
    'rychloregMail'                         =>
        'Ahoj,

děkujeme, že ses letos zúčastnil{a} GameConu. Kliknutím na odkaz níže potvrdíš registraci na web a můžeš si nastavit přezdívku a heslo, pokud chceš používat web a třeba přijet příští rok. (Pokud by ses registroval{a} na web později, musel{a} by sis nechat vygenerovat heslo znova)

<a href="https://gamecon.cz/potvrzeni-registrace/%2">https://gamecon.cz/potvrzeni-registrace/%2</a>',
    Hlaska::NEDOSTAVENI_SE_NA_AKTIVITU_MAIL =>
        'Ahoj,

vypadá to, že aktivita %1 proběhla bez tebe!

Chápeme, že může nastat situace, kvůli které se nemůžeš, nebo nechceš aktivity zúčastnit, pro příště tě ale moc prosíme

VŽDY SE Z AKTIVITY ODHLAS.

Žádáme tě o to z toho důvodu, aby na tebe vypravěč a ostatní účastníci nemuseli čekat a zjišťovat, jestli dorazíš. A taky to značně urychlí hledání náhradníka. Pokud se odhlásíš více než %2 před začátkem aktivity, vrátíme ti za ni část peněz.

Jedná se o omyl? Někde v systému pravděpodobně nastala chyba, za kterou se moc omlouváme. Dej nám o ní prosím vědět, abychom to opravili a znovu tě zbytečně neděsili.

Děkujeme za spolupráci!

Tvůj,
Organizační tým GC',
];
