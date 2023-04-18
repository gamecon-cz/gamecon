function shopVstupne(input, range, posuvnik, minimum, smajliky) {
    const gamma = Number.parseFloat(document.getElementById('vstupneGamaKorekce').dataset.vstupneGamaKorekce);
    const rangeMinimum = prevedNaPomer(minimum)

    input.onchange = () => {
        input.value = omez(input.value, minimum, 99999)
        range.value = prevedNaPomer(input.value)
        prekresli()
    }

    range.oninput = () => {
        range.value = omez(range.value, rangeMinimum, 99999)
        input.value = prevedNaHodnotu(range.value)
        prekresli()
    }

    function prevedNaPomer(castka) {
        let pomer = castka / 1000
        pomer = omez(pomer, 0, 1)
        pomer = Math.pow(pomer, gamma)
        return pomer
    }

    function prevedNaHodnotu(pomer) {
        let skutecnyPomer = Math.pow(pomer, 1 / gamma)
        return Math.round(skutecnyPomer * 1000)
    }

    function omez(cislo, min, max) {
        return Math.min(Math.max(cislo, min), max)
    }

    function prekresli() {
        let procento = Math.round(range.value * 100)
        posuvnik.style.background = 'linear-gradient(to right, #E22630, #E22630 ' + procento + '%, #737373 ' + procento + '%)'

        input.style.backgroundImage = smajlik(input.value)
    }

    function smajlik(castka) {
        for (let i = 0; i < smajliky.length; i++) {
            if (castka >= smajliky[i][0]) {
                return 'url(' + smajliky[i][1] + ')'
            }
        }
    }

    // počáteční refresh
    input.onchange()

    // preloader smajlíků
    const preloader = document.createElement('div')
    const pozadi = smajliky.map(e => `url('${e[1]}')`).join(',')
    preloader.setAttribute('style', `
        height: 10px;
        width: 10px;
        position: absolute;
        opacity: 0.01;
        background: ${pozadi};
    `);
    input.after(preloader)
}
