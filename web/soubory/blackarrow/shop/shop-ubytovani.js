{
    var shopUbytovaniRadios = document.querySelectorAll('input[type=radio][class=shopUbytovani_radio]');
    var shopUbytovaniNames = [];
    var zmeneneElementy = [];

    // 1) Načtení ze sessionStorage (pokud tam nic není, zůstane null)
    let stored = sessionStorage.getItem('presKapacituBtn');

    // 2) Pokud tam hodnota je, použij ji, jinak NENASTAVUJ nic do sessionStorage
    let presKapacituBtn = stored !== null ? JSON.parse(stored) : null;

    // Po načtení stránky aplikuj stav (pokud byl zapnutý)
    window.addEventListener('DOMContentLoaded', () => {
        if (presKapacituBtn) {
            zobrazPovinnePolozky();
            aplikujPresKapacitu();
        }
    });

    shopUbytovaniRadios.forEach(function (shopUbytovaniRadio) {
        if (!shopUbytovaniNames.includes(shopUbytovaniRadio.name)) {
            shopUbytovaniNames.push(shopUbytovaniRadio.name);
        }
    });

    function zapamatujKapacituJakoRucneZvolenou(radioInput) {
        var zvolenaKapacita = radioInput.dataset.kapacita;
        var radiaJednohoDne = document.querySelectorAll('input[type=radio][class=shopUbytovani_radio][name="' + radioInput.name + '"]');
        radiaJednohoDne.forEach(radioJednohoDne => radioJednohoDne.dataset.kapacitaZvolenaUzivatelem = zvolenaKapacita);
    }

    function onShopUbytovaniChange() {
        zmeneneElementy = [];
        zmeneneElementy.push(this);
        zapamatujKapacituJakoRucneZvolenou(this);
        obnovPovinnePolozky();
        var zvolenaKapacita = this.dataset.kapacita;
        var zvolenaKapacitaInt = Number.parseInt(zvolenaKapacita);
        if (zvolenaKapacitaInt === 0) {
            return;
        }
        var zvolenyTyp = this.dataset.typ;
        var zvoleneName = this.name;
        var ostatniNames = shopUbytovaniNames.filter(name => name !== zvoleneName);
        ostatniNames.forEach(function (ostatniName) {
            var ostatniZvoleneUbytovani = document.querySelector('input[type=radio][class=shopUbytovani_radio][name="' + ostatniName + '"]:checked');
            if (ostatniZvoleneUbytovani
                && ostatniZvoleneUbytovani.dataset.kapacitaZvolenaUzivatelem !== undefined
                && Number.parseInt(ostatniZvoleneUbytovani.dataset.kapacitaZvolenaUzivatelem) === 0
            ) {
                return; // v tomto dni je uzivatelem rucne vybrano Zadne ubytovani, to nechceme menit
            }
            var ostatniStejneUbytovaniInput = ubytovaniInput(ostatniName, zvolenyTyp);
            if (!ostatniStejneUbytovaniInput.disabled) {
                ostatniStejneUbytovaniInput.checked = true;
                zmeneneElementy.push(ostatniStejneUbytovaniInput);
            } else {
                // v tomto dni je cilova kapacita jiz vycerpana, vybereme proto Zadne ubytovani (kapacita 0)
                vyberZadneUbytovani(ostatniName);
            }
        });
    }

    function ubytovaniInput(inputName, typUbytovani) {
        return document.querySelector('input[type=radio][class=shopUbytovani_radio][name="' + inputName + '"][data-typ="' + typUbytovani + '"]');
    }

    function vyberZadneUbytovani(inputName) {
        var zadneUbytovaniInput = ubytovaniInput(inputName, 'Žádné');
        zadneUbytovaniInput.checked = true;
        zmeneneElementy.forEach(function (predtimZmenenyElement, index) {
            if (predtimZmenenyElement.name === zadneUbytovaniInput.name) {
                zmeneneElementy.splice(index, 1); // odstraníme předchozí výběr pro stejný den
            }
        });
        zmeneneElementy.push(zadneUbytovaniInput);
    }

    function onShopUbytovaniClick() {
        /** @var {HTMLInputElement} kliknutyInput */
        var kliknutyInput = this;
        if (kliknutyInput.disabled) {
            return;
        }
        if (!kliknutyInput.checked) {
            return;
        }
        if (kliknutyInput.dataset.typ === 'Žádné') {
            return;
        }
        // click event je pred onchange, zmeneneElementy pochází z předchozího výběru
        zmeneneElementy.forEach(function (zmenenyElement) {
            if (zmenenyElement.name === kliknutyInput.name
                && zmenenyElement.dataset.typ === kliknutyInput.dataset.typ
            ) {
                vyberZadneUbytovani(kliknutyInput.name);
            }
        });
    }

    function zobrazPovinnePolozky() {
        prepniPovinnePolozky(true);
    }

    function prepniPovinnePolozky(show) {
        Array.from(document.getElementsByClassName('shopUbytovani_povinne'))
            .forEach(function (povinnyElement) {
                povinnyElement.style.display = show ? 'inherit' : 'none';
                Array.from(povinnyElement.querySelectorAll('input, select'))
                    .forEach((input) => {
                        // required jen pokud zobrazujeme a nejsme v režimu přes kapacitu
                        input.required = show && !presKapacituBtn;
                    });
            });
    }

    function skryjPovinnePolozky() {
        prepniPovinnePolozky(false);
    }

    function obnovPovinnePolozky() {
        let nejakeUbytovaniVybrano = false;
        shopUbytovaniRadios.forEach(function (shopUbytovaniRadio) {
            if (shopUbytovaniRadio.checked) {
                nejakeUbytovaniVybrano = nejakeUbytovaniVybrano || shopUbytovaniRadio.dataset.typ !== 'Žádné';
            }
        });
        if (nejakeUbytovaniVybrano) {
            zobrazPovinnePolozky();
        } else {
            skryjPovinnePolozky();
        }
    }

    shopUbytovaniRadios.forEach(function (shopUbytovaniRadio) {
        shopUbytovaniRadio.addEventListener('change', onShopUbytovaniChange);

        if (shopUbytovaniRadio.checked) {
            zapamatujKapacituJakoRucneZvolenou(shopUbytovaniRadio);
            zmeneneElementy.push(shopUbytovaniRadio); // výchozí stav pro "odškrtávání"
        }

        shopUbytovaniRadio.addEventListener('click', onShopUbytovaniClick);
    });

    // TOGGLE funkce pro tlačítko "přes kapacitu"
    function presKapacitu() {
        // přepneme stav (null/false -> true, true -> false)
        presKapacituBtn = !presKapacituBtn;

        if (presKapacituBtn) {
            // ZAPNUTO "přes kapacitu"
            sessionStorage.setItem('presKapacituBtn', JSON.stringify(true));
            prepniPovinnePolozky(true);
            aplikujPresKapacitu();
        } else {
            // VYPNUTO "přes kapacitu"
            sessionStorage.removeItem('presKapacituBtn');
            skryjPovinnePolozky();
            obnovKapacituPoPresKapacite();
        }
    }

    function aplikujPresKapacitu() {
        document.querySelectorAll('input.shopUbytovani_radio').forEach(el => {
            if (el.disabled) {
                // zapamatujeme si, že byl původně disabled
                el.dataset.disabledPuvodne = "1";
                el.removeAttribute('disabled');
            }
        });
    }

    function obnovKapacituPoPresKapacite() {
        document.querySelectorAll('input.shopUbytovani_radio').forEach(el => {
            if (el.dataset.disabledPuvodne === "1") {
                el.disabled = true;
            }
        });
    }

    obnovPovinnePolozky();
}
