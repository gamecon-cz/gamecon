// Čtečka QR kódu v Nastavení: načte kód pro přihlášení jiného zařízení a otevře stránku jeho schválení.
// Fotoaparát telefonu umí totéž, odkaz ale otevře ve výchozím prohlížeči, a ten nemusí být ten, kde je uživatel
// přihlášený. Kód rozpozná BarcodeDetector prohlížeče; kde ho prohlížeč nemá (Safari, Firefox), knihovna jsQR,
// načtená až v tu chvíli. Viz Gamecon\Uzivatel\PrihlaseniQrKodem a docs/generated/prihlaseni-qr-kodem.md.
// Web skript vkládá do hlavičky s defer (Modul::pridejJsSoubor()), admin na konec stránky. Kdyby běžel dřív,
// než prohlížeč přečte celou stránku, své prvky by nenašel, proto počká na DOMContentLoaded. Po něm (async, vložený
// dodatečně) by na tu událost čekal marně, tak se spustí hned.
(function (spust) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', spust);
    } else {
        spust();
    }
})(function () {
    'use strict';

    var tlacitko = document.querySelector('[data-ctecka-qr]');
    if (!tlacitko) {
        return;
    }

    var okno = document.querySelector('[data-ctecka-qr-okno]');
    var video = okno.querySelector('video');
    var zprava = okno.querySelector('[data-ctecka-qr-zprava]');
    var zavrit = okno.querySelector('[data-ctecka-qr-zavrit]');
    // adresa stránky schválení bez parametrů, jinam čtečka nikoho nepošle
    var adresaSchvaleni = tlacitko.getAttribute('data-adresa-schvaleni');
    var adresaJsQr = tlacitko.getAttribute('data-jsqr');
    var stream = null;
    var bezi = false;
    // Každé spuštění kamery má své číslo. Kamera spuštěná pod číslem, které už neplatí (okno se mezitím zavřelo,
    // nebo se kamera spustila znovu dřív, než prohlížeč odpověděl na první žádost), se hned zastaví.
    var spusteni = 0;
    var platno = null;

    function napis(text) {
        zprava.textContent = text;
    }

    function zastavStream(zastavovany) {
        zastavovany.getTracks().forEach(function (stopa) {
            stopa.stop();
        });
    }

    function zastavKameru() {
        bezi = false;
        spusteni++;
        if (stream) {
            zastavStream(stream);
            stream = null;
        }
        video.srcObject = null;
    }

    function zavriOkno() {
        zastavKameru();
        okno.hidden = true;
        tlacitko.hidden = false;
        tlacitko.focus();
    }

    function nactiJsQr() {
        if (window.jsQR) {
            return Promise.resolve(window.jsQR);
        }
        return new Promise(function (vyres, odmitni) {
            var skript = document.createElement('script');
            skript.src = adresaJsQr;
            skript.onload = function () {
                if (window.jsQR) {
                    vyres(window.jsQR);
                } else {
                    odmitni(new Error('jsQR se nenačetla'));
                }
            };
            skript.onerror = odmitni;
            document.head.appendChild(skript);
        });
    }

    // Vrátí funkci, která přečte jeden snímek videa: Promise s textem kódu, nebo null.
    function cteniJsQr() {
        return nactiJsQr().then(function (jsQR) {
            platno = platno || document.createElement('canvas');
            var kontext = platno.getContext('2d', {willReadFrequently: true});
            return function () {
                var sirka = video.videoWidth;
                var vyska = video.videoHeight;
                if (!sirka || !vyska) {
                    return Promise.resolve(null);
                }
                // menší snímek se rozpozná rychleji a QR kód z obrazovky na něm pořád je
                var meritko = Math.min(1, 640 / Math.max(sirka, vyska));
                platno.width = Math.round(sirka * meritko);
                platno.height = Math.round(vyska * meritko);
                kontext.drawImage(video, 0, 0, platno.width, platno.height);
                var obraz = kontext.getImageData(0, 0, platno.width, platno.height);
                var kod = jsQR(obraz.data, obraz.width, obraz.height, {inversionAttempts: 'dontInvert'});
                return Promise.resolve(kod ? kod.data : null);
            };
        });
    }

    function vytvorCteni() {
        if (!('BarcodeDetector' in window)) {
            return cteniJsQr();
        }
        return window.BarcodeDetector.getSupportedFormats()
            .then(function (formaty) {
                if (formaty.indexOf('qr_code') === -1) {
                    return cteniJsQr();
                }
                var detektor = new window.BarcodeDetector({formats: ['qr_code']});
                return function () {
                    return detektor.detect(video).then(function (kody) {
                        return kody.length ? kody[0].rawValue : null;
                    });
                };
            })
            .catch(cteniJsQr);
    }

    function zpracuj(text) {
        var adresa = null;
        try {
            adresa = new URL(text);
        } catch (chyba) {
            adresa = null;
        }
        if (adresa && adresa.origin + adresa.pathname === adresaSchvaleni) {
            zastavKameru();
            napis('Kód načten, otevírám schválení…');
            window.location.assign(adresa.href);
            return true;
        }
        napis('Tohle není QR kód pro přihlášení do GameConu. Namiř na kód na přihlašovací stránce.');
        return false;
    }

    // platne() říká, jestli kamera, pro kterou se čte, pořád běží
    function ctiStale(cti, platne) {
        if (!platne()) {
            return;
        }
        cti()
            .then(function (text) {
                if (platne() && !(text && zpracuj(text))) {
                    window.setTimeout(function () {
                        ctiStale(cti, platne);
                    }, 250);
                }
            })
            .catch(function () {
                window.setTimeout(function () {
                    ctiStale(cti, platne);
                }, 500);
            });
    }

    function spust() {
        tlacitko.hidden = true;
        okno.hidden = false;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            napis('Tenhle prohlížeč fotoaparát nepoužije. Naskenuj kód aplikací Fotoaparát.');
            return;
        }
        napis('Namiř fotoaparát na QR kód na přihlašovací stránce.');
        bezi = true;
        var totoSpusteni = ++spusteni;
        var platne = function () {
            return bezi && totoSpusteni === spusteni;
        };
        navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}, audio: false})
            .then(function (novyStream) {
                if (!platne()) {
                    // okno mezitím zavřel, nebo kameru spustil znovu: tahle kamera nikomu nepatří
                    zastavStream(novyStream);
                    return null;
                }
                stream = novyStream;
                video.srcObject = novyStream;
                return video.play().then(vytvorCteni);
            })
            .then(function (cti) {
                if (cti && platne()) {
                    ctiStale(cti, platne);
                }
            })
            .catch(function (chyba) {
                if (!platne()) {
                    // chyba kamery, která už neběží, novější spuštění nezastaví
                    return;
                }
                zastavKameru();
                napis(chyba && chyba.name === 'NotAllowedError'
                    ? 'Bez přístupu k fotoaparátu to nepůjde. Povol ho v nastavení prohlížeče, nebo naskenuj kód aplikací Fotoaparát.'
                    : 'Fotoaparát se nepodařilo spustit. Naskenuj kód aplikací Fotoaparát.');
            });
    }

    tlacitko.addEventListener('click', spust);
    zavrit.addEventListener('click', zavriOkno);
    // kamera neběží na pozadí
    document.addEventListener('visibilitychange', function () {
        if (document.hidden && !okno.hidden) {
            zavriOkno();
        }
    });
});
