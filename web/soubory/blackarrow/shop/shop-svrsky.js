{
    const hodnotaPrazdnePolozky = '0'
    const vsechnyOpakovaneSelecty = () => Array.from(document.querySelectorAll('.shopPredmety_opakovanySelect'))
    const vyberySkupiny = skupina => Array.from(document.querySelectorAll(`.shopPredmety_opakovanySelect[data-opakovany-select="${skupina}"]`))

    function indexVyberu(selectNode) {
        const shoda = selectNode.name.match(/\[(\d+)\]/)
        return shoda ? Number.parseInt(shoda[1], 10) : 0
    }

    function nastavIndexVyberu(polozkaNode, index, skupina) {
        const selectNode = polozkaNode.querySelector(`.shopPredmety_opakovanySelect[data-opakovany-select="${skupina}"]`)
        if (!selectNode) {
            return
        }

        const idPrefix = polozkaNode.dataset.inputIdPrefix || 'vyberPolozky'
        selectNode.name = selectNode.name.replace(/\[\d+\]/, `[${index}]`)
        selectNode.id = `${idPrefix}-${index}`

        const labelNode = polozkaNode.querySelector('label')
        if (labelNode) {
            labelNode.setAttribute('for', selectNode.id)
        }
    }

    function jePosledniVyberSkupiny(selectNode, skupina) {
        const vybery = vyberySkupiny(skupina)
        return vybery[vybery.length - 1] === selectNode
    }

    function dalsiIndexVyberuSkupiny(skupina) {
        return vyberySkupiny(skupina).reduce(
            (nejvyssiIndex, selectNode) => Math.max(nejvyssiIndex, indexVyberu(selectNode)),
            -1,
        ) + 1
    }

    function pridejVyberDalsiPolozky(event) {
        const selectNode = event.currentTarget
        const skupina = selectNode.dataset.opakovanySelect
        if (!skupina
            || selectNode.value === hodnotaPrazdnePolozky
            || !jePosledniVyberSkupiny(selectNode, skupina)
        ) {
            return
        }

        const polozkaNode = selectNode.closest(`.shopPredmety_opakovanyVyber[data-opakovany-vyber="${skupina}"]`)
        if (!polozkaNode) {
            return
        }

        const klon = polozkaNode.cloneNode(true)
        nastavIndexVyberu(klon, dalsiIndexVyberuSkupiny(skupina), skupina)

        const klonSelect = klon.querySelector(`.shopPredmety_opakovanySelect[data-opakovany-select="${skupina}"]`)
        if (!klonSelect) {
            return
        }
        klonSelect.value = hodnotaPrazdnePolozky
        klonSelect.addEventListener('change', pridejVyberDalsiPolozky)

        polozkaNode.after(klon)
    }

    vsechnyOpakovaneSelecty().forEach(selectNode => {
        selectNode.addEventListener('change', pridejVyberDalsiPolozky)
    })
}
