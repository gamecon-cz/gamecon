{
    const hodnotaZadneTricko = '0'
    const vyberyTricek = () => Array.from(document.querySelectorAll('.shopPredmety_trickoSelect'))

    function indexVyberu(selectNode) {
        const shoda = selectNode.name.match(/\[(\d+)\]/)
        return shoda ? Number.parseInt(shoda[1], 10) : 0
    }

    function nastavIndexVyberu(trickoNode, index) {
        const selectNode = trickoNode.querySelector('.shopPredmety_trickoSelect')
        if (!selectNode) {
            return
        }

        selectNode.name = selectNode.name.replace(/\[\d+\]/, `[${index}]`)
        selectNode.id = `vyberTricek-${index}`

        const labelNode = trickoNode.querySelector('label')
        if (labelNode) {
            labelNode.setAttribute('for', selectNode.id)
        }
    }

    function jePosledniVyberTricka(selectNode) {
        const vybery = vyberyTricek()
        return vybery[vybery.length - 1] === selectNode
    }

    function dalsiIndexVyberuTricka() {
        return vyberyTricek().reduce(
            (nejvyssiIndex, selectNode) => Math.max(nejvyssiIndex, indexVyberu(selectNode)),
            -1,
        ) + 1
    }

    function pridejVyberDalsihoTricka(event) {
        const selectNode = event.currentTarget
        if (selectNode.value === hodnotaZadneTricko || !jePosledniVyberTricka(selectNode)) {
            return
        }

        const trickoNode = selectNode.closest('.shopPredmety_tricko')
        if (!trickoNode) {
            return
        }

        const klon = trickoNode.cloneNode(true)
        nastavIndexVyberu(klon, dalsiIndexVyberuTricka())

        const klonSelect = klon.querySelector('.shopPredmety_trickoSelect')
        if (!klonSelect) {
            return
        }
        klonSelect.value = hodnotaZadneTricko
        klonSelect.addEventListener('change', pridejVyberDalsihoTricka)

        trickoNode.after(klon)
    }

    vyberyTricek().forEach(selectNode => {
        selectNode.addEventListener('change', pridejVyberDalsihoTricka)
    })
}
