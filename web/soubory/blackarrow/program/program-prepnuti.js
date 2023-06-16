/**
 * @param {Element} zalozkaElement
 */
function programPrepnuti(zalozkaElement) {
  zalozkaElement.addEventListener('click', function (event) {
    event.preventDefault()
    document.querySelector('.program_den-aktivni').classList.remove('program_den-aktivni')
    this.classList.add('program_den-aktivni')

    document.querySelector('.program_den_detail-aktivni').classList.remove('program_den_detail-aktivni')
    var hash = this.hash
    var kodProgramu = hash.replace('#', '')
    var idProgramu = `programDenDetail-${kodProgramu}`
    document.getElementById(idProgramu).classList.add('program_den_detail-aktivni')
  })
}
