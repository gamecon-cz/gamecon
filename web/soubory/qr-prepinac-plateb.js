(function () {
    var prepinacePlateb = document.querySelectorAll('[data-qr-prepinac-plateb]');

    for (var prepinacIndex = 0; prepinacIndex < prepinacePlateb.length; prepinacIndex++) {
        nastavPrepinac(prepinacePlateb[prepinacIndex]);
    }

    function nastavPrepinac(kontejner) {
        var tlacitka = kontejner.querySelectorAll('[data-qr-tlacitko]');
        var karty = kontejner.querySelectorAll('[data-qr-karta]');
        // Eurová část souhrnného textu je mimo tento kontejner, proto ji hledáme v celém dokumentu.
        var euroTexty = document.querySelectorAll('[data-qr-eur-text]');
        var vychoziTyp = kontejner.getAttribute('data-qr-prepinac-plateb') || 'cz';

        var nastavAktivniKartu = function (typQrPlatby) {
            for (var kartaIndex = 0; kartaIndex < karty.length; kartaIndex++) {
                var karta = karty[kartaIndex];
                karta.hidden = karta.getAttribute('data-qr-karta') !== typQrPlatby;
            }
            for (var tlacitkoIndex = 0; tlacitkoIndex < tlacitka.length; tlacitkoIndex++) {
                var tlacitko = tlacitka[tlacitkoIndex];
                var jeAktivni = tlacitko.getAttribute('data-qr-tlacitko') === typQrPlatby;
                tlacitko.classList.toggle('is-aktivni', jeAktivni);
                tlacitko.setAttribute('aria-pressed', jeAktivni ? 'true' : 'false');
            }
            // CZ platba je v korunách – eurovou částku i poznámku k ní schováme.
            var jenCeskaKoruna = typQrPlatby === 'cz';
            for (var euroIndex = 0; euroIndex < euroTexty.length; euroIndex++) {
                euroTexty[euroIndex].hidden = jenCeskaKoruna;
            }
        };

        for (var tlacitkoIndex = 0; tlacitkoIndex < tlacitka.length; tlacitkoIndex++) {
            tlacitka[tlacitkoIndex].addEventListener('click', function () {
                nastavAktivniKartu(this.getAttribute('data-qr-tlacitko'));
            });
        }

        nastavAktivniKartu(vychoziTyp);
    }
})();
