/**
 * Uloží scroll stránky (a případně prvku), obnoví ho zachovejScroll() při dalším načtení stránky.
 * Volá se samo i pro odkaz, který stránku opustí až po potvrzení (Aktivita::getPrihlasovatkoSUpozornenimNaVek()):
 * kdyby se scroll uložil už kliknutím a uživatel pak potvrzení zrušil, obnovil by se až na jiné stránce.
 *
 * @param prvek extra prvek, jehož vodorovný scroll se ukládá (nebo null)
 */
function zachovejScrollUloz(prvek) {
    window.localStorage.setItem('zachovejScroll_top', window.scrollY)
    if (prvek) {
        window.localStorage.setItem('zachovejScroll_left', prvek.scrollLeft)
    }
}

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

    function storagePop(klic) {
        let hodnota = window.localStorage.getItem('zachovejScroll_' + klic)
        window.localStorage.removeItem('zachovejScroll_' + klic)
        return hodnota
    }

    scrollObnov(obnovovanyPrvek)

    onclickPrvky.forEach(e => {
        e.addEventListener('click', () => zachovejScrollUloz(obnovovanyPrvek))
    })

}
