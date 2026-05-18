{
    var shopUbytovaniRadios = document.querySelectorAll('input[type=radio][class=shopUbytovani_radio]');
    var shopUbytovaniNames = [];
    var zmeneneElementy = [];
    var volbaNechciUbytovani = document.querySelector('.shopUbytovani_nechci');
    var volbaNechciUbytovaniCheckbox = document.querySelector('input.shopUbytovani_nechciCheckbox');
    var tabulkaUbytovani = document.querySelector('.shopUbytovani_tabulka');
    var upozorneniUbytovani = document.querySelector('.shopUbytovani_upozorneni');
    var formularUbytovani = tabulkaUbytovani ? tabulkaUbytovani.closest('form') : null;
    var spolubydliciBlok = document.querySelector('.shopUbytovani_spolubydlici');
    var infoUbytovaniBlok = document.querySelector('.prihlaska_infoPruh-ubytovani');

    // 1) Načtení ze sessionStorage (pokud tam nic není, zůstane null)
    let stored = sessionStorage.getItem('presKapacituBtn');

    // 2) Pokud tam hodnota je, použij ji, jinak NENASTAVUJ nic do sessionStorage
    let presKapacituBtn = stored !== null ? JSON.parse(stored) : null;

    // Po načtení stránky aplikuj stav (pokud byl zapnutý z předchozího načtení)
    window.addEventListener('DOMContentLoaded', () => {
        obnovStavUbytovani();
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
        var zvolenaKapacita = this.dataset.kapacita;
        var zvolenaKapacitaInt = Number.parseInt(zvolenaKapacita);
        if (zvolenaKapacitaInt === 0) {
            obnovStavUbytovani();
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
        obnovStavUbytovani();
    }

    /**
     * @param {string} inputName
     * @param {string} typUbytovani
     * @return {HTMLInputElement}
     */
    function ubytovaniInput(inputName, typUbytovani) {
        return document.querySelector('input[type=radio][class=shopUbytovani_radio][name="' + inputName + '"][data-typ="' + typUbytovani + '"]');
    }

    /**
     * @param {string} inputName
     * @param {boolean} obnovitPovinnePolozky
     */
    function vyberZadneUbytovani(inputName, obnovitPovinnePolozky = true) {
        var zadneUbytovaniInput = ubytovaniInput(inputName, 'Žádné');
        zadneUbytovaniInput.checked = true;
        zmeneneElementy.forEach(function (predtimZmenenyElement, index) {
            if (predtimZmenenyElement.name === zadneUbytovaniInput.name) {
                zmeneneElementy.splice(index, 1); // odstraníme předchozí výběr pro stejný den
            }
        });
        zmeneneElementy.push(zadneUbytovaniInput);
        if (obnovitPovinnePolozky) {
            obnovStavUbytovani();
        }
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

    /** @param {boolean} zobrazit */
    function prepniUbytovaniTabulku(zobrazit) {
        if (!tabulkaUbytovani) {
            return;
        }
        tabulkaUbytovani.style.display = zobrazit ? '' : 'none';
        var sekce = tabulkaUbytovani.closest('.prihlaska_sekce');
        if (!sekce) {
            return;
        }
        if (!zobrazit) {
            var infoPruh = sekce.querySelector('.prihlaska_infoPruh');
            sekce.style.minHeight = infoPruh ? infoPruh.offsetHeight + 'px' : '';
        } else {
            sekce.style.minHeight = '';
        }
    }

    /** @param {boolean} zobrazit */
    function prepniSpolubydlici(zobrazit) {
        if (!spolubydliciBlok) {
            return;
        }
        spolubydliciBlok.style.display = zobrazit ? '' : 'none';
    }

    /** @param {boolean} zobrazit */
    function prepniInfoUbytovani(zobrazit) {
        if (!infoUbytovaniBlok) {
            return;
        }
        infoUbytovaniBlok.style.display = zobrazit ? '' : 'none';
        if (!zobrazit) {
            var sekce = infoUbytovaniBlok.closest('.prihlaska_sekce');
            if (sekce) {
                sekce.style.minHeight = '';
            }
        }
    }

    function onVolbaNechciUbytovaniChange() {
        if (!volbaNechciUbytovaniCheckbox) {
            return;
        }

        if (volbaNechciUbytovaniCheckbox.checked) {
            zmeneneElementy = [];
            shopUbytovaniNames.forEach(function (name) {
                vyberZadneUbytovani(name, false);
            });
            obnovStavUbytovani();
            prepniUbytovaniTabulku(false);
            prepniSpolubydlici(false);
            prepniInfoUbytovani(false);
            return;
        }

        prepniUbytovaniTabulku(true);
        prepniSpolubydlici(true);
        prepniInfoUbytovani(true);
    }

    function zobrazPovinnePolozky() {
        prepniPovinnePolozky(true);
    }

    /** @param {boolean} show */
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
        prepniVolbuNechciUbytovani(!nejakeUbytovaniVybrano);
    }

    function obnovStavUbytovani() {
        obnovPovinnePolozky();
        obnovUpozorneniNaVybraneNoci();
    }

    /**
     * @return {number[]}
     */
    function vybraneDnyUbytovani() {
        var vybraneDny = [];
        shopUbytovaniRadios.forEach(function (radio) {
            if (!radio.checked || radio.dataset.typ === 'Žádné') {
                return;
            }
            var match = radio.name.match(/\[(\d+)]/);
            if (!match) {
                return;
            }
            var den = Number.parseInt(match[1]);
            if (!vybraneDny.includes(den)) {
                vybraneDny.push(den);
            }
        });
        vybraneDny.sort(function (a, b) {
            return a - b;
        });
        return vybraneDny;
    }

    /**
     * @param {number[]} vybraneDny
     * @return {boolean}
     */
    function nociNavazuji(vybraneDny) {
        for (var i = 1; i < vybraneDny.length; i++) {
            if (vybraneDny[i] !== vybraneDny[i - 1] + 1) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param {number} pocet
     * @return {string}
     */
    function tvarNoci(pocet) {
        if (pocet === 1) {
            return 'noc';
        }
        if (pocet >= 2 && pocet <= 4) {
            return 'noci';
        }
        return 'nocí';
    }

    function skryjUpozorneniNaVybraneNoci() {
        if (!upozorneniUbytovani) {
            return;
        }
        upozorneniUbytovani.textContent = '';
        upozorneniUbytovani.style.display = 'none';
    }

    /**
     * @param {string} zprava
     */
    function zobrazUpozorneniNaVybraneNoci(zprava) {
        if (!upozorneniUbytovani) {
            return;
        }
        upozorneniUbytovani.textContent = zprava;
        upozorneniUbytovani.style.display = '';
    }

    function zpravaValidaceVybranychNoci() {
        if (!upozorneniUbytovani) {
            return '';
        }

        var minimalniPocetNoci = Number.parseInt(upozorneniUbytovani.dataset.minimalniPocetNoci || '2');
        var vybraneDny = vybraneDnyUbytovani();
        if (vybraneDny.length === 0) {
            return '';
        }

        if (vybraneDny.length < minimalniPocetNoci) {
            var chybiNoci = minimalniPocetNoci - vybraneDny.length;
            return 'Vyber ještě ' + chybiNoci + ' navazující ' + tvarNoci(chybiNoci) + ' ubytování.';
        }

        if (vybraneDny.length > 1 && !nociNavazuji(vybraneDny)) {
            return 'Vybrané noci ubytování na sebe musí navazovat.';
        }

        return '';
    }

    function obnovUpozorneniNaVybraneNoci() {
        var zprava = zpravaValidaceVybranychNoci();
        if (zprava) {
            zobrazUpozorneniNaVybraneNoci(zprava);
            return;
        }

        skryjUpozorneniNaVybraneNoci();
    }

    function onFormularUbytovaniSubmit(event) {
        var submitter = event.submitter || document.activeElement;
        var submitterName = submitter && submitter.name ? submitter.name : '';
        if (submitterName === 'odhlasit') {
            return;
        }
        if (submitterName && submitterName !== 'prihlasitNeboUpravit' && submitterName !== 'zpracujUbytovani') {
            return;
        }

        var zprava = zpravaValidaceVybranychNoci();
        if (!zprava) {
            return;
        }

        event.preventDefault();
        zobrazUpozorneniNaVybraneNoci(zprava);
        upozorneniUbytovani.scrollIntoView();
    }

    /**
     * @param {boolean} zobrazit
     */
    function prepniVolbuNechciUbytovani(zobrazit) {
        if (!volbaNechciUbytovani) {
            return;
        }
        volbaNechciUbytovani.style.display = zobrazit ? 'block' : 'none';
        if (!zobrazit && volbaNechciUbytovaniCheckbox) {
            volbaNechciUbytovaniCheckbox.checked = false;
            prepniUbytovaniTabulku(true);
            prepniSpolubydlici(true);
            prepniInfoUbytovani(true);
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

    if (volbaNechciUbytovaniCheckbox) {
        volbaNechciUbytovaniCheckbox.addEventListener('change', onVolbaNechciUbytovaniChange);
    }
    if (formularUbytovani) {
        formularUbytovani.addEventListener('submit', onFormularUbytovaniSubmit);
    }

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
                el.dataset.disabledPuvodne = "1";
                el.removeAttribute('disabled');
            }
        });
        aktualizujTlacitkoPresKapacitu(true);
    }

    function obnovKapacituPoPresKapacite() {
        document.querySelectorAll('input.shopUbytovani_radio').forEach(el => {
            if (el.dataset.disabledPuvodne === "1") {
                el.disabled = true;
                delete el.dataset.disabledPuvodne;
            }
        });
        aktualizujTlacitkoPresKapacitu(false);
    }

    function aktualizujTlacitkoPresKapacitu(aktivni) {
        var btn = document.querySelector('input[onClick="presKapacitu()"]');
        if (btn) {
            btn.value = aktivni ? 'zrušit přes kapacitu' : 'přes kapacitu';
        }
    }

    obnovStavUbytovani();
    var chceUbytovani = !(volbaNechciUbytovaniCheckbox && volbaNechciUbytovaniCheckbox.checked);
    prepniUbytovaniTabulku(chceUbytovani);
    prepniSpolubydlici(chceUbytovani);
    prepniInfoUbytovani(chceUbytovani);
}
