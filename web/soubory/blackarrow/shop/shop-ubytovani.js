{
    var shopUbytovaniRadios = document.querySelectorAll('input[type=radio][class=shopUbytovani_radio]')
    var shopUbytovaniNames = []
    var zmeneneElementy = []

    shopUbytovaniRadios.forEach(function (shopUbytovaniRadio) {
        if (!shopUbytovaniNames.includes(shopUbytovaniRadio.name)) {
            shopUbytovaniNames.push(shopUbytovaniRadio.name)
        }
    })

    function zapamatujKapacituJakoRucneZvolenou(radioInput) {
        var zvolenaKapacita = radioInput.dataset.kapacita
        var radiaJednohoDne = document.querySelectorAll('input[type=radio][class=shopUbytovani_radio][name="' + radioInput.name + '"]')
        radiaJednohoDne.forEach(radioJednohoDne => radioJednohoDne.dataset.kapacitaZvolenaUzivatelem = zvolenaKapacita)
    }

    function onShopUbytovaniChange() {
        zmeneneElementy = []
        zmeneneElementy.push(this)
        zapamatujKapacituJakoRucneZvolenou(this)
        obnovPovinnePolozky()
        var zvolenaKapacita = this.dataset.kapacita
        var zvolenaKapacitaInt = Number.parseInt(zvolenaKapacita)
        if (zvolenaKapacitaInt === 0) {
            return
        }
        var zvolenyTyp = this.dataset.typ
        var zvoleneName = this.name
        var ostatniNames = shopUbytovaniNames.filter(name => name !== zvoleneName)
        ostatniNames.forEach(function (ostatniName) {
            var ostatniZvoleneUbytovani = document.querySelector('input[type=radio][class=shopUbytovani_radio][name="' + ostatniName + '"]:checked')
            if (ostatniZvoleneUbytovani
                && ostatniZvoleneUbytovani.dataset.kapacitaZvolenaUzivatelem !== undefined
                && Number.parseInt(ostatniZvoleneUbytovani.dataset.kapacitaZvolenaUzivatelem) === 0
            ) {
                return // v tomto dni je uzivatelem rucne vybrano Zadne ubytovani, to nechceme menit
            }
            var ostatniStejneUbytovaniInput = ubytovaniInput(ostatniName, zvolenyTyp)
            if (!ostatniStejneUbytovaniInput.disabled) {
                ostatniStejneUbytovaniInput.checked = true
                zmeneneElementy.push(ostatniStejneUbytovaniInput)
            } else {
                // v tomto dni je cilova kapacita jiz vycerpana, vybereme proto Zadne ubytovani (kapacita 0)
                vyberZadneUbytovani(ostatniName)
            }
        })
    }

    /**
     * @param {string} inputName
     * @param {string} typUbytovani
     * @return {HTMLInputElement}
     */
    function ubytovaniInput(inputName, typUbytovani) {
        return document.querySelector('input[type=radio][class=shopUbytovani_radio][name="' + inputName + '"][data-typ="' + typUbytovani + '"]')
    }

    /**
     * @param {string} inputName
     */
    function vyberZadneUbytovani(inputName) {
        var zadneUbytovaniInput = ubytovaniInput(inputName, 'Žádné')
        zadneUbytovaniInput.checked = true
        zmeneneElementy.forEach(function (predtimZmenenyElement, index) {
            if (predtimZmenenyElement.name === zadneUbytovaniInput.name) {
                zmeneneElementy.splice(index, 1) // odstraníme ze seznamu předchozí výběr ubytování ve stejný den jako je teď vybrané "Žádné" ubytování
            }
        })
        zmeneneElementy.push(zadneUbytovaniInput)
    }

    function onShopUbytovaniClick() {
        /** @var {HTMLInputElement} kliknutyInput */
        var kliknutyInput = this
        if (kliknutyInput.disabled) {
            return
        }
        if (!kliknutyInput.checked) {
            return
        }
        if (kliknutyInput.dataset.typ === 'Žádné') {
            return
        }
        // click event je pred onchange, zmeneneElementy pochází z předchozího, už dokončeného výběru
        zmeneneElementy.forEach(function (zmenenyElement) {
            if (zmenenyElement.name === kliknutyInput.name // například "shopUbytovaniDny[0]"
                && zmenenyElement.dataset.typ === kliknutyInput.dataset.typ // například "Trojlůžák"
            ) {
                vyberZadneUbytovani(kliknutyInput.name)
            }
        })
    }

    function zobrazPovinnePolozky() {
        prepniPovinnePolozky(true)
    }

    /**
     * @param {boolean} show
     */
    function prepniPovinnePolozky(show) {
        Array.from(document.getElementsByClassName('shopUbytovani_povinne')).forEach(function (povinnyElement) {
            povinnyElement.style.display = show
                ? 'inherit'
                : 'none'
            Array.from(povinnyElement.querySelectorAll('input, select')).forEach((input) => input.required = show)
        })
    }

    function skryjPovinnePolozky() {
        prepniPovinnePolozky(false)
    }

    function obnovPovinnePolozky() {
        let nejakeUbytovaniVybrano = false
        shopUbytovaniRadios.forEach(function (shopUbytovaniRadio) {
            if (shopUbytovaniRadio.checked) {
                nejakeUbytovaniVybrano = nejakeUbytovaniVybrano || shopUbytovaniRadio.dataset.typ !== 'Žádné'
            }
        })
        if (nejakeUbytovaniVybrano) {
            zobrazPovinnePolozky()
        } else {
            skryjPovinnePolozky()
        }
    }

    shopUbytovaniRadios.forEach(function (shopUbytovaniRadio) {
        shopUbytovaniRadio.addEventListener('change', onShopUbytovaniChange)

        if (shopUbytovaniRadio.checked) {
            zapamatujKapacituJakoRucneZvolenou(shopUbytovaniRadio)
            zmeneneElementy.push(shopUbytovaniRadio) // abychom měli výchozí stav pro "odškrtávání"
        }

        shopUbytovaniRadio.addEventListener('click', onShopUbytovaniClick)
    })

    obnovPovinnePolozky()
}
