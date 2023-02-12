/* logika k situaci, že není zaškrtnutý ani jeden účastník k aktivitě  */
function zadnyUcastnikNeniPotvrzen() {
  const checkboxy = document.querySelectorAll(`#ucastniciAktivity${posledneKliknutaAktivitaId} input.dorazil[checked]`)
  return checkboxy.length === 0
}

function potvrdModal() {
  if (zadnyUcastnikNeniPotvrzen()) {
    if (!document.getElementById('potvrzujiBezUcastniku').checked) {
      document.getElementById('labelPotvrzujiBezUcastniku').classList.add('fw-bold')
      return
    }
  }
  document.getElementById('online-prezence').dispatchEvent(
    new CustomEvent('uzavritAktivitu', {detail: posledneKliknutaAktivitaId}),
  )
  jQuery('#modalOpravduUzavrit').modal('hide');
}

document.addEventListener('DOMContentLoaded', function () {
  Array.from(document.getElementsByClassName('tlacitko-uzavrit-aktivitu')).forEach(el => {
    el.addEventListener('click', function () {
      if (zadnyUcastnikNeniPotvrzen()) {
        // žádné vybrané aktivity
        document.getElementById('wrapperPotvrzujiBezUcastniku').classList.remove('d-none')
        return
      }
      document.getElementById('wrapperPotvrzujiBezUcastniku').classList.add('d-none')
    })
  })

  $('#modalOpravduUzavrit').on('hidden.bs.modal', function () {
    document.getElementById('labelPotvrzujiBezUcastniku').classList.remove('fw-bold')
  })
})
