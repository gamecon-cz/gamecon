/**
 * @param {Element} zalozkaElement
 */
function initializujProgramPrepnuti(zalozkaElement) {
  zalozkaElement.addEventListener('click', function (event) {
    event.preventDefault()
    document.querySelector('.program_den-aktivni').classList.remove('program_den-aktivni')
    this.classList.add('program_den-aktivni')

    document.querySelector('.program_den_detail-aktivni').classList.remove('program_den_detail-aktivni')
    var hash = this.hash
    var kodProgramu = hash.replace('#', '')
    var idProgramu = `programDenDetail-${kodProgramu}`
    document.getElementById(idProgramu).classList.add('program_den_detail-aktivni')

    setCookie('aktivni-program-den', this.id)
  })
}

document.addEventListener('programNacteny', function () {
  var aktivniProgramDenId = getCookie('aktivni-program-den')
  if (aktivniProgramDenId) {
    var zalozkaElement = document.getElementById(aktivniProgramDenId)
    if (zalozkaElement) {
      zalozkaElement.dispatchEvent(new Event('click'))
    }
  }
})
