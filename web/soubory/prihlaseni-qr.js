// Přihlášení QR kódem: přihlašovací stránka webu i adminu se ptá, jestli už přihlášení někdo na telefonu
// schválil. Schválené přihlášení dokončí server rovnou v odpovědi, stránce pak stačí přejít dál.
// Viz Gamecon\Uzivatel\PrihlaseniQrKodem a docs/generated/prihlaseni-qr-kodem.md.
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

    var kontejner = document.querySelector('[data-prihlaseni-qr]');
    if (!kontejner) {
        return;
    }

    var INTERVAL_MS = 2000;
    var stavUrl = kontejner.getAttribute('data-stav-url');
    // prázdné = obnovit stránku (admin zůstane na stránce, kterou chtěl otevřít)
    var poPrihlaseni = kontejner.getAttribute('data-po-prihlaseni');
    var zprava = kontejner.querySelector('[data-prihlaseni-qr-zprava]');
    var koncoveZpravy = {
        zamitnuto: 'Přihlášení na telefonu někdo zamítl, nebo vybral jiné číslo. Vygeneruj nový kód.',
        vyprselo: 'Kód vypršel, vygeneruj nový.',
        zadna: 'Kód už neplatí, vygeneruj nový.',
    };

    function skonci(stav) {
        kontejner.classList.add('prihlaseniQr-konec');
        zprava.textContent = koncoveZpravy[stav] || koncoveZpravy.zadna;
    }

    function zeptejSe() {
        fetch(stavUrl, {credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}})
            .then(function (odpoved) {
                if (!odpoved.ok) {
                    throw new Error('HTTP ' + odpoved.status);
                }
                return odpoved.json();
            })
            .then(function (data) {
                if (data.stav === 'prihlaseno') {
                    if (poPrihlaseni) {
                        window.location.assign(poPrihlaseni);
                    } else {
                        window.location.reload();
                    }
                    return;
                }
                if (data.stav === 'ceka') {
                    window.setTimeout(zeptejSe, INTERVAL_MS);
                    return;
                }
                skonci(data.stav);
            })
            .catch(function () {
                // výpadek sítě nebo serveru, zkusí to znovu o chvíli později
                window.setTimeout(zeptejSe, INTERVAL_MS * 2);
            });
    }

    window.setTimeout(zeptejSe, INTERVAL_MS);
});
