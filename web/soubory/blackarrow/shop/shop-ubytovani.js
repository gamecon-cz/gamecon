{
    const shopUbytovaniRadios = document.querySelectorAll('input[type=radio][class=shopUbytovani_radio]')
    const shopUbytovaniNames = []
    let zmeneneElementy = []

    shopUbytovaniRadios.forEach(function (shopUbytovaniRadio) {
        if (!shopUbytovaniNames.includes(shopUbytovaniRadio.name)) {
            shopUbytovaniNames.push(shopUbytovaniRadio.name)
        }
    })

    function zapamatujKapacituJakoRucneZvolenou(radioInput) {
        const zvolenaKapacita = radioInput.dataset.kapacita
        const radiaJednohoDne = document.querySelectorAll('input[type=radio][class=shopUbytovani_radio][name="' + radioInput.name + '"]')
        radiaJednohoDne.forEach(radioJednohoDne => radioJednohoDne.dataset.kapacitaZvolenaUzivatelem = zvolenaKapacita)
    }

    function onShopUbytovaniChange() {
        zmeneneElementy = []
        zmeneneElementy.push(this)
        zapamatujKapacituJakoRucneZvolenou(this)
        const zvolenaKapacita = this.dataset.kapacita
        const zvolenaKapacitaInt = Number.parseInt(zvolenaKapacita)
        if (zvolenaKapacitaInt === 0) {
            return
        }
        const zvolenyTyp = this.dataset.typ
        const zvoleneName = this.name
        const ostatniNames = shopUbytovaniNames.filter(name => name !== zvoleneName)
        ostatniNames.forEach(function (ostatniName) {
            const ostatniZvoleneUbytovani = document.querySelector('input[type=radio][class=shopUbytovani_radio][name="' + ostatniName + '"]:checked')
            if (ostatniZvoleneUbytovani
                && ostatniZvoleneUbytovani.dataset.kapacitaZvolenaUzivatelem !== undefined
                && Number.parseInt(ostatniZvoleneUbytovani.dataset.kapacitaZvolenaUzivatelem) === 0
            ) {
                return // v tomto dni je uzivatelem rucne vybrano Zadne ubytovani, to nechceme menit
            }
            const ostatniStejneUbytovaniInput = ubytovaniInput(ostatniName, zvolenyTyp)
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
        const zadneUbytovaniInput = ubytovaniInput(inputName, 'Žádné')
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
        const kliknutyInput = this
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

    shopUbytovaniRadios.forEach(function (shopUbytovaniRadio) {
        shopUbytovaniRadio.addEventListener('change', onShopUbytovaniChange)
        if (shopUbytovaniRadio.checked) {
            zapamatujKapacituJakoRucneZvolenou(shopUbytovaniRadio)
            zmeneneElementy.push(shopUbytovaniRadio) // abychom měli výchozí stav pro "odškrtávání"
        }
        shopUbytovaniRadio.addEventListener('click', onShopUbytovaniClick)
    })
}
