// Service worker webu GameConu: přijímá push notifikace, i když je web zavřený, a po kliknutí otevře stránku.
// Nic neukládá do cache a do načítání stránek nezasahuje.
//
// Leží v kořeni webu, aby jeho scope byl celý web: na ostré a preview /sw.js, lokálně /web/sw.js. Adresy si proto
// odvozuje ze svého scope, ne z pevné cesty. Registruje ho soubory/push-notifikace.js až poté, co uživatel v Nastavení
// klikne na Zapnout notifikace. Koncept: docs/generated/web-push-notifikace.md.
(function (sw) {
    'use strict';

    var API = 'api/pushSubscription';
    var IKONA = 'soubory/push/ikona-192.png';
    var VYCHOZI_NADPIS = 'GameCon';

    /**
     * Adresa, kterou smí kliknutí na notifikaci otevřít: jen stránka tohoto webu. Odkaz kontroluje už server
     * (Gamecon\PushNotifikace\OdkazNotifikace), tady podruhé: notifikace vypadá jako zpráva od GameConu a nesmí vést
     * jinam ani spustit skript, ani kdyby se do ní něco takového dostalo. Cokoli jiného otevře úvodní stránku.
     */
    function bezpecnaAdresa(adresa, scope) {
        if (typeof adresa !== 'string' || adresa === '') {
            return scope;
        }
        try {
            var cil = new URL(adresa, scope);
            var web = new URL(scope);
            if ((cil.protocol === 'https:' || cil.protocol === 'http:')
                && cil.origin === web.origin
                && cil.href.indexOf(web.href) === 0
            ) {
                return cil.href;
            }
        } catch (chyba) {
            // neplatná adresa
        }

        return scope;
    }

    /** Co poslal server (Gamecon\PushNotifikace\ZpravaNotifikace::getPayload()), bezpečně přečtené. */
    function prectiZpravu(data) {
        var zprava = {};
        try {
            zprava = data ? data.json() : {};
        } catch (chyba) {
            zprava = {};
        }
        if (zprava === null || typeof zprava !== 'object') {
            zprava = {};
        }

        return {
            nadpis: typeof zprava.title === 'string' && zprava.title !== '' ? zprava.title : VYCHOZI_NADPIS,
            text: typeof zprava.body === 'string' ? zprava.body : '',
            adresa: typeof zprava.url === 'string' ? zprava.url : '',
            tag: typeof zprava.tag === 'string' && zprava.tag !== '' ? zprava.tag : undefined,
        };
    }

    /**
     * Příchozí push: ukáže notifikaci. Každý push musí skončit viditelnou notifikací, jinak prohlížeč subscription
     * časem zruší (userVisibleOnly), proto ji ukáže i tehdy, když obsah nejde přečíst.
     *
     * Serveru se tu schválně neptá, jestli notifikace pořád patří přihlášenému: požadavek ze service workeru by
     * zařízení bez běžící session přihlásil cookie trvalého přihlášení a vyměnil její token, a odpověď ztracená
     * na špatném signálu by pak uživatele odhlásila (docs/generated/prihlaseni-na-vice-zarizenich.md).
     */
    function zpracujPush(sluzba, data) {
        var scope = sluzba.registration.scope;
        var zprava = prectiZpravu(data);

        return sluzba.registration.showNotification(zprava.nadpis, {
            body: zprava.text,
            icon: scope + IKONA,
            tag: zprava.tag,
            // notifikace se stejným tagem starší nahradí; bez renotify by ta nová přišla potichu
            renotify: zprava.tag !== undefined,
            data: { adresa: zprava.adresa },
        });
    }

    // pro testy (ui/src/pushNotifikace/serviceWorker.test.ts)
    sw.gcPushServiceWorker = { bezpecnaAdresa: bezpecnaAdresa, prectiZpravu: prectiZpravu, zpracujPush: zpracujPush };

    sw.addEventListener('install', function () {
        // nová verze nečeká, až se zavřou všechny karty: nic necachuje, není co rozbít
        sw.skipWaiting();
    });

    sw.addEventListener('activate', function (udalost) {
        udalost.waitUntil(sw.clients.claim());
    });

    sw.addEventListener('push', function (udalost) {
        udalost.waitUntil(zpracujPush(sw, udalost.data));
    });

    sw.addEventListener('notificationclick', function (udalost) {
        var scope = sw.registration.scope;
        var cil = bezpecnaAdresa(udalost.notification.data && udalost.notification.data.adresa, scope);
        udalost.notification.close();
        udalost.waitUntil(
            sw.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (okna) {
                for (var i = 0; i < okna.length; i++) {
                    if (okna[i].url === cil && 'focus' in okna[i]) {
                        return okna[i].focus();
                    }
                }

                return sw.clients.openWindow(cil);
            }),
        );
    });

    // Prohlížeč subscription sám vyměnil (vypršela, push služba ji zrušila). Server se to jinak nedozví a posílal by
    // na starou. Požadavek nese cookie přihlášení; kdo už přihlášený není, novou subscription nezaregistruje.
    // Když prohlížeč starou subscription nepředá (Chrome to tak dělává), není serveru co říct: novou nezná, stránka
    // ji při další návštěvě zruší a uživatel si notifikace zapne znovu.
    sw.addEventListener('pushsubscriptionchange', function (udalost) {
        var stara = udalost.oldSubscription;
        var nova = udalost.newSubscription
            ? Promise.resolve(udalost.newSubscription)
            : (stara && stara.options
                ? sw.registration.pushManager.subscribe(stara.options)
                : Promise.resolve(null));
        udalost.waitUntil(nova.then(function (subscription) {
            if (!subscription || !stara) {
                return null;
            }

            return fetch(sw.registration.scope + API, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    akce: 'zmena',
                    staryEndpoint: stara.endpoint,
                    subscription: subscription.toJSON(),
                }),
            });
        }).catch(function () {
            // bez spojení nebo bez přihlášení: neznámou subscription stránka při příští návštěvě zruší
        }));
    });
})(self);
