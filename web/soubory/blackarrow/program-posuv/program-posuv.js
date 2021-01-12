function programPosuv(obal2) {
    const posun = 220

    let lposuv = document.createElement('div')
    lposuv.innerHTML = '<div></div>'
    lposuv.className = 'programPosuv_posuv programPosuv_lposuv'
    lposuv.style.display = 'none'

    let rposuv = document.createElement('div')
    rposuv.innerHTML = '<div></div>'
    rposuv.className = 'programPosuv_posuv programPosuv_rposuv'
    rposuv.style.display = 'none'

    let obal = obal2.getElementsByClassName('programPosuv_obal')[0]

    lposuv.firstElementChild.onclick = () => obal.scrollBy({left: -posun, behavior: 'smooth'})
    rposuv.firstElementChild.onclick = () => obal.scrollBy({left:  posun, behavior: 'smooth'})
    obal.onscroll = () => checkScroll()

    obal2.append(lposuv)
    obal2.append(rposuv)

    checkScroll()
    new ResizeObserver(checkScroll).observe(obal)

    function checkScroll() {
        let left = obal.scrollLeft
        if (left <= 0) {
            ldisplay('none')
        } else {
            ldisplay('block')
        }

        let innerWidth = obal.scrollWidth
        let outerWidth = obal.clientWidth
        let right = innerWidth - (left + outerWidth)
        if (right <= 0) {
            rdisplay('none')
        } else {
            rdisplay('block')
        }
    }

    var soucasnyLdisplay = lposuv.style.display
    function ldisplay(val) {
        if (soucasnyLdisplay != val) {
            lposuv.style.display = val
            soucasnyLdisplay = val
        }
    }

    var soucasnyRdisplay = rposuv.style.display
    function rdisplay(val) {
        if (soucasnyRdisplay != val) {
            rposuv.style.display = val
            soucasnyRdisplay = val
        }
    }
}
