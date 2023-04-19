const shopUbytovaniRadios = document.querySelectorAll('input[type=radio][class=shopUbytovani_radio]')
const shopUbytovaniNames = []
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
        const ostatniStejneUbytovani = document.querySelector('input[type=radio][class=shopUbytovani_radio][name="' + ostatniName + '"][data-typ="' + zvolenyTyp + '"]')
        if (!ostatniStejneUbytovani.disabled) {
            ostatniStejneUbytovani.checked = true
        } else {
            document.querySelector('input[type=radio][class=shopUbytovani_radio][name="' + ostatniName + '"][data-typ="Žádné"]').checked = true // v tomto dni je cilova kapacita jiz vycerpana, vybereme proto Zadne ubytovani (kapacita 0)
        }
    })
}

shopUbytovaniRadios.forEach(function (shopUbytovaniRadio) {
    shopUbytovaniRadio.addEventListener('change', onShopUbytovaniChange)
    if (shopUbytovaniRadio.checked) {
        zapamatujKapacituJakoRucneZvolenou(shopUbytovaniRadio)
    }
})
