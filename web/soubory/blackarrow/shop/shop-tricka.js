{
    pridejVyberDalsihoTricka = function (selectNode) {
        selectNode.addEventListener('change', function () {
            if (this.value === '0') { // žádné tričko
                return
            }
            const klon = this.cloneNode(true)
            this.id = null // ID přebírá klon, aby na něj fungoval label
            klon.value = 0 // vybereme "žádné tričko"
            const puvodniNazev = this.name
            const puvodniIndexPhpPole = Number.parseInt(puvodniNazev.match(/\[(\d+)]/)[1])
            const novyIndexPhpPole = puvodniIndexPhpPole + 1
            const novyNazev = puvodniNazev.replace(`[${puvodniIndexPhpPole}]`, `[${novyIndexPhpPole}]`)
            klon.name = novyNazev
            this.removeEventListener('change', pridejVyberDalsihoTricka)
            pridejVyberDalsihoTricka(klon) // štafetu, kdy přibyde další výběr, přebírá nový výběr zatím bez trička
            this.parentElement.append(klon)
        })
    }
    const vyberTricek = document.getElementById('vyberTricek')
    pridejVyberDalsihoTricka(vyberTricek)
}
