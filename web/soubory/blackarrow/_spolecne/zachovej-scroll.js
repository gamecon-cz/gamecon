/**
 * Uloží/obnoví scroll stránky (a případně prvku).
 *
 * @param onclickPrvky prvky, kde se při kliknutí scroll zapamatuje (odkazy)
 * @param obnovovanyPrvek extra prvek, jehož scroll se obnovuje (scroll prvku
 *        window se obnovuje sám by default)
 */
function zachovejScroll(onclickPrvky, obnovovanyPrvek) {

    function scrollObnov(prvek) {
        let top = storagePop('top')
        if (top) {
            window.scrollTo({top: top})
        }

        let left = storagePop('left')
        if (left && prvek) {
            prvek.scrollLeft = left
        }
    }

    function scrollUloz(prvek) {
        storagePush('top', window.scrollY)
        if (prvek) {
            storagePush('left', prvek.scrollLeft)
        }
    }

    function storagePush(klic, hodnota) {
        window.localStorage.setItem('zachovejScroll_' + klic, hodnota)
    }

    function storagePop(klic) {
        let hodnota = window.localStorage.getItem('zachovejScroll_' + klic)
        window.localStorage.removeItem('zachovejScroll_' + klic)
        return hodnota
    }

    scrollObnov(obnovovanyPrvek)

    onclickPrvky.forEach(e => {
        e.addEventListener('click', () => scrollUloz(obnovovanyPrvek))
    })

}
