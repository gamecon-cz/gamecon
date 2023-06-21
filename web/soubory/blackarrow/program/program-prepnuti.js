/**
 * @param {HTMLAnchorElement} zalozkaElement
 */
function initializujProgramPrepnuti(zalozkaElement) {
  zalozkaElement.addEventListener('click', function (/** @type {CustomEvent} */event) {
    event.preventDefault()
    document.querySelector('.program_den-aktivni').classList.remove('program_den-aktivni')
    this.classList.add('program_den-aktivni')

    document.querySelector('.program_den_detail-aktivni').classList.remove('program_den_detail-aktivni')
    var hash = this.hash
    var kodProgramu = hash.replace('#', '')
    var idProgramu = `programDenDetail-${kodProgramu}`
    document.getElementById(idProgramu).classList.add('program_den_detail-aktivni')

    var titulek = zalozkaElement.dataset.titulek
    if (titulek) {
      document.getElementsByTagName("title")[0].innerHTML = titulek
    }

    setCookie('aktivni-program-den', this.id)

    if (!event.detail || event.detail.type !== 'virtual') {
      history.pushState({id: event.target.id, redirect: 'virtual'}, null, zalozkaElement.href)
    }
  })
}

function kliknoutNaZalozkuProgramu(programDenId) {
  var zalozkaElement = document.getElementById(programDenId)
  zalozkaElement.dispatchEvent(new CustomEvent('click', {detail: {type: 'virtual'}}))
}

document.addEventListener('programNacteny', function () {
  var pouzitHistoriiZCookie = true
  Array.from(document.getElementsByClassName('program_den')).forEach(function (element) {
    if (window.location.href.endsWith(element.href)) { // například 'https://gamecon.cz/program/sobota#sobota' končí na 'program/sobota#sobota'
      pouzitHistoriiZCookie = false
    }
  })
  if (pouzitHistoriiZCookie) {
    var aktivniProgramDenId = getCookie('aktivni-program-den')
    if (aktivniProgramDenId) {
      kliknoutNaZalozkuProgramu(aktivniProgramDenId)
    }
  }
})

window.addEventListener("popstate", (event) => {
  if (event.state && event.state.redirect === 'virtual' && event.state.id) {
    kliknoutNaZalozkuProgramu(event.state.id)
  }
});
