// Push notifikace na tomto zařízení: ovládání v Nastavení (Gamecon\Uzivatel\Registrace) a na každé stránce
// přihlášeného kontrola, jestli subscription v prohlížeči pořád patří jemu.
//
// Stav je složený ze dvou půlek. Prohlížeč ví, jestli notifikace umí, jestli je uživatel povolil a jestli je web
// na ploše (iOS). Server (api/pushSubscription) ví, jestli je subscription zapnutá pro přihlášeného uživatele a jeho
// přihlášení na tomto zařízení. Když server subscription z prohlížeče nezná, patří předchozímu uživateli zařízení
// nebo skončenému přihlášení a prohlížeč ji zruší.
//
// O oprávnění žádá až kliknutí na tlačítko, nikdy načtení stránky. Koncept: docs/generated/web-push-notifikace.md.
// Psáno bez novější syntaxe: starý prohlížeč má ukázat „nepodporováno“, ne spadnout na chybě syntaxe.
(function (spust) {
    'use strict';

    /**
     * Umí prohlížeč web push? Na iPhonu a iPadu jen web přidaný na plochu (iOS 16.4+), Safari v prohlížeči ne.
     *
     * @returns {'ano'|'ios-na-plochu'|'ne'}
     */
    function zjistiPodporu(okno) {
        var navigator = okno.navigator || {};
        if ('serviceWorker' in navigator
            && 'PushManager' in okno
            && 'Notification' in okno
            && typeof okno.Promise === 'function'
            && typeof okno.fetch === 'function'
        ) {
            return 'ano';
        }
        var jeIos = /iPad|iPhone|iPod/.test(navigator.userAgent || '')
            // iPad se hlásí jako Mac, pozná se jen podle dotykové obrazovky
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        var jeNaPlose = navigator.standalone === true
            || (typeof okno.matchMedia === 'function' && okno.matchMedia('(display-mode: standalone)').matches);

        return jeIos && !jeNaPlose ? 'ios-na-plochu' : 'ne';
    }

    /**
     * Stav, který uvidí uživatel. Co nejde změnit tlačítkem (nepodporováno, cizí účet, zamítnuto), má přednost.
     *
     * @param {{podpora: string, opravneni: string, stavServeru: string, maSubscription: boolean}} vstup
     * @returns {'nepodporovano'|'ios-na-plochu'|'prepnuto'|'bez-trvaleho-prihlaseni'|'zamitnuto'|'zapnuto'|'vypnuto'}
     */
    function urciStav(vstup) {
        if (vstup.podpora === 'ios-na-plochu') {
            return 'ios-na-plochu';
        }
        if (vstup.podpora !== 'ano') {
            return 'nepodporovano';
        }
        if (vstup.stavServeru === 'prepnuto') {
            return 'prepnuto';
        }
        if (vstup.stavServeru === 'bez-trvaleho-prihlaseni') {
            return 'bez-trvaleho-prihlaseni';
        }
        if (vstup.opravneni === 'denied') {
            return 'zamitnuto';
        }

        return vstup.stavServeru === 'zapnuto' && vstup.maSubscription && vstup.opravneni === 'granted'
            ? 'zapnuto'
            : 'vypnuto';
    }

    /** Veřejný VAPID klíč (base64url) jako bajty, jak ho chce PushManager.subscribe(). */
    function naBajty(base64url) {
        var base64 = (base64url + '===='.slice((base64url.length + 3) % 4 + 1)).replace(/-/g, '+').replace(/_/g, '/');
        var znaky = atob(base64);
        var bajty = new Uint8Array(znaky.length);
        for (var i = 0; i < znaky.length; i++) {
            bajty[i] = znaky.charCodeAt(i);
        }

        return bajty;
    }

    /** Co stránka ke stavu napíše. `tlacitko` chybí tam, kde uživatel nemá co zmáčknout. */
    var TEXTY = {
        'zapnuto': {
            stav: 'Notifikace jsou na tomto zařízení zapnuté.',
            napoveda: ['Přijdou, i když máš web zavřený a telefon zamčený. Skončí, když se tu odhlásíš.'],
            tlacitko: 'Vypnout notifikace',
        },
        'vypnuto': {
            stav: 'Notifikace jsou na tomto zařízení vypnuté.',
            napoveda: ['Po zapnutí se tě prohlížeč zeptá, jestli je GameConu povolíš. Platí jen pro toto zařízení.'],
            tlacitko: 'Zapnout notifikace',
        },
        'zamitnuto': {
            stav: 'Notifikace z GameConu jsou v tomto prohlížeči zakázané.',
            napoveda: [
                'Povolit je musíš v prohlížeči, stránka to za tebe udělat nemůže:',
                'Na počítači klikni na ikonu vlevo od adresy stránky, najdi Oznámení a zvol Povolit.',
                'Na Androidu v prohlížeči otevři Nastavení, Nastavení webu, Oznámení a GameCon povol.',
                'Na iPhonu a iPadu otevři Nastavení telefonu, Oznámení, GameCon a oznámení povol.',
                'Pak tuhle stránku načti znovu.',
            ],
        },
        'nepodporovano': {
            stav: 'Tento prohlížeč notifikace z webu neumí.',
            napoveda: [
                'Zkus aktuální Chrome, Edge, Firefox nebo Safari. V anonymním okně notifikace nefungují.',
                'Na iPhonu a iPadu je potřeba iOS 16.4 nebo novější a web přidaný na plochu.',
            ],
        },
        'ios-na-plochu': {
            stav: 'Na iPhonu a iPadu chodí notifikace jen z webu přidaného na plochu.',
            napoveda: [
                '1. V Safari klepni na Sdílet (čtvereček se šipkou nahoru).',
                '2. Zvol Přidat na plochu.',
                '3. Otevři GameCon z plochy, přihlas se a notifikace zapni tady v Nastavení.',
                'Je potřeba iOS 16.4 nebo novější.',
            ],
        },
        'prepnuto': {
            stav: 'Jsi přepnutý(á) na cizí účet, jeho notifikace tady nastavit nejdou.',
            napoveda: [],
        },
        'bez-trvaleho-prihlaseni': {
            stav: 'Notifikace jdou zapnout jen na zařízení, kde jsi přihlášen(a) trvale.',
            napoveda: ['Odhlas se a přihlas znovu se zaškrtnutým „Trvale přihlásit“.'],
        },
    };

    // pro testy (ui/src/pushNotifikace/pushNotifikace.test.ts)
    window.gcPushNotifikace = { zjistiPodporu: zjistiPodporu, urciStav: urciStav, naBajty: naBajty, TEXTY: TEXTY };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            spust(zjistiPodporu, urciStav, naBajty, TEXTY);
        });
    } else {
        spust(zjistiPodporu, urciStav, naBajty, TEXTY);
    }
})(function (zjistiPodporu, urciStav, naBajty, TEXTY) {
    'use strict';

    var konfigurace = document.querySelector('meta[name="gc-push-notifikace"]');
    if (!konfigurace) {
        return;
    }
    var adresaApi = konfigurace.getAttribute('data-api');
    var adresaServiceWorkeru = konfigurace.getAttribute('data-service-worker');
    var scope = konfigurace.getAttribute('data-scope');
    var vapidKlic = konfigurace.getAttribute('data-vapid-klic');

    // jen v Nastavení; jinde skript jen hlídá, čí subscription prohlížeč nese
    var panel = document.querySelector('[data-push-notifikace]');
    var podpora = zjistiPodporu(window);
    // Mimo Nastavení se server ptá nejvýš jednou za tuhle dobu, ne s každou stránkou. Každý uživatel zvlášť:
    // kdo se na zařízení přihlásí po někom jiném, ověří se hned první stránkou.
    var KLIC_OVERENO = 'gcPushNotifikaceOvereno:' + konfigurace.getAttribute('data-uzivatel');
    var OVERIT_PO_MS = 10 * 60 * 1000;

    function posli(akce, data) {
        var telo = data || {};
        telo.akce = akce;

        return fetch(adresaApi, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(telo),
        }).then(function (odpoved) {
            return odpoved.json().then(function (obsah) {
                if (!odpoved.ok) {
                    throw new Error(obsah && obsah.chyba ? obsah.chyba : 'Server odpověděl chybou.');
                }

                return obsah;
            });
        });
    }

    /** Subscription, kterou prohlížeč pro tento web má, null když žádnou. Service worker kvůli tomu neregistruje. */
    function nactiSubscription() {
        return navigator.serviceWorker.getRegistration(scope).then(function (registrace) {
            return registrace ? registrace.pushManager.getSubscription() : null;
        });
    }

    /** Zeptá se serveru na stav a zruší subscription, kterou server pro tohoto uživatele nezná. */
    function srovnejSeServerem(subscription) {
        return posli('stav', { endpoint: subscription ? subscription.endpoint : null }).then(function (odpoved) {
            if (odpoved.zrusitVProhlizeci && subscription) {
                return subscription.unsubscribe().then(function () {
                    return { stavServeru: odpoved.stav, subscription: null };
                });
            }

            return { stavServeru: odpoved.stav, subscription: subscription };
        });
    }

    function vykresli(stav, chyba) {
        if (!panel) {
            return;
        }
        var texty = TEXTY[stav];
        var prvekStavu = panel.querySelector('[data-push-stav]');
        var napoveda = panel.querySelector('[data-push-napoveda]');
        var tlacitko = panel.querySelector('[data-push-tlacitko]');
        var prvekChyby = panel.querySelector('[data-push-chyba]');

        panel.setAttribute('data-push-notifikace', stav);
        prvekStavu.textContent = texty.stav;
        napoveda.textContent = '';
        for (var i = 0; i < texty.napoveda.length; i++) {
            var radek = document.createElement('p');
            radek.textContent = texty.napoveda[i];
            napoveda.appendChild(radek);
        }
        napoveda.hidden = texty.napoveda.length === 0;
        tlacitko.hidden = !texty.tlacitko;
        tlacitko.disabled = false;
        tlacitko.textContent = texty.tlacitko || '';
        prvekChyby.textContent = chyba || '';
        prvekChyby.hidden = !chyba;
    }

    function obnovPanel(chyba) {
        if (podpora !== 'ano') {
            vykresli(urciStav({ podpora: podpora }));

            return Promise.resolve();
        }

        return nactiSubscription().then(srovnejSeServerem).then(function (vysledek) {
            vykresli(urciStav({
                podpora: podpora,
                opravneni: Notification.permission,
                stavServeru: vysledek.stavServeru,
                maSubscription: vysledek.subscription !== null,
            }), chyba);
        }).catch(function (duvod) {
            vykresli('vypnuto', chyba || (duvod && duvod.message) || 'Stav notifikací se nepodařilo zjistit.');
        });
    }

    /** Starší Safari vrací výsledek jen zpětným voláním, novější prohlížeče slibem. */
    function pozadejOOpravneni() {
        return new Promise(function (vyres) {
            var slib = Notification.requestPermission(vyres);
            if (slib && typeof slib.then === 'function') {
                slib.then(vyres);
            }
        });
    }

    function zalozSubscription(registrace) {
        var volby = { userVisibleOnly: true, applicationServerKey: naBajty(vapidKlic) };

        return registrace.pushManager.subscribe(volby).catch(function () {
            // Prohlížeč má subscription s jiným klíčem (klíče prostředí se změnily): zrušit a založit znovu.
            return registrace.pushManager.getSubscription().then(function (stara) {
                return stara ? stara.unsubscribe() : null;
            }).then(function () {
                return registrace.pushManager.subscribe(volby);
            });
        });
    }

    function zapni() {
        // Žádost o oprávnění musí být první věc po kliknutí, jinak ji Safari nebere jako akci uživatele.
        return pozadejOOpravneni().then(function (opravneni) {
            if (opravneni !== 'granted') {
                return obnovPanel();
            }

            return navigator.serviceWorker.register(adresaServiceWorkeru, { scope: scope }).then(function () {
                return navigator.serviceWorker.ready;
            }).then(zalozSubscription).then(function (subscription) {
                return posli('zapnout', { subscription: subscription.toJSON() }).then(function () {
                    return obnovPanel();
                }, function (duvod) {
                    // server ji nepřijal: nenechat v prohlížeči subscription, o které server neví
                    return subscription.unsubscribe().then(function () {
                        return obnovPanel(duvod.message);
                    });
                });
            });
        });
    }

    function vypni() {
        return nactiSubscription().then(function (subscription) {
            if (!subscription) {
                return obnovPanel();
            }

            return posli('vypnout', { endpoint: subscription.endpoint }).then(function () {
                return subscription.unsubscribe();
            }).then(function () {
                return obnovPanel();
            });
        });
    }

    if (panel) {
        panel.querySelector('[data-push-tlacitko]').addEventListener('click', function (udalost) {
            var tlacitko = udalost.currentTarget;
            var akce = panel.getAttribute('data-push-notifikace') === 'zapnuto' ? vypni : zapni;
            tlacitko.disabled = true;
            akce().catch(function (duvod) {
                return obnovPanel((duvod && duvod.message) || 'Nepodařilo se to, zkus to prosím znovu.');
            });
        });
        obnovPanel();

        return;
    }

    if (podpora !== 'ano') {
        return;
    }
    try {
        if (Date.now() - Number(sessionStorage.getItem(KLIC_OVERENO) || 0) < OVERIT_PO_MS) {
            return;
        }
    } catch (chyba) {
        // sessionStorage nedostupné (soukromé okno): ověří se s každou stránkou
    }
    nactiSubscription().then(function (subscription) {
        if (!subscription) {
            return null;
        }

        return srovnejSeServerem(subscription).then(function () {
            try {
                sessionStorage.setItem(KLIC_OVERENO, String(Date.now()));
            } catch (chyba) {
                // viz výš
            }
        });
    }).catch(function () {
        // bez spojení: zkusí to další stránka
    });
});
