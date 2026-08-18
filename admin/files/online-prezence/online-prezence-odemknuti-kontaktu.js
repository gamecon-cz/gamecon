/**
 * Souhlas se zobrazením kontaktů účastníků (e-maily, telefony).
 *
 * Rozhodnutí drží server v session, ne cookie ani localStorage – jinak by si
 * odemčení nastavil i ten, kdo souhlas odklikat nechce, a do logu by se to
 * nepropsalo. Po potvrzení proto stránku znovu načteme a kontakty vykreslí
 * server; klient sám nic neodkrývá.
 */
document.addEventListener('DOMContentLoaded', function () {
  const modalNode = document.getElementById('modalOdemknoutKontakty')
  if (!modalNode) {
    return
  }

  const checkboxNode = document.getElementById('souhlasSPodminkamiKontaktu')
  const potvrzovaciTlacitkoNode = document.getElementById('potvrditOdemceniKontaktu')
  let odemykanaAktivitaId = null

  Array.from(document.getElementsByClassName('odemknout-kontakty')).forEach(function (tlacitkoNode) {
    tlacitkoNode.addEventListener('click', function () {
      odemykanaAktivitaId = tlacitkoNode.dataset.idAktivity
    })
  })

  checkboxNode.addEventListener('change', function () {
    potvrzovaciTlacitkoNode.disabled = !checkboxNode.checked
  })

  jQuery(modalNode).on('hidden.bs.modal', function () {
    checkboxNode.checked = false
    potvrzovaciTlacitkoNode.disabled = true
  })

  potvrzovaciTlacitkoNode.addEventListener('click', function () {
    if (!checkboxNode.checked || !odemykanaAktivitaId) {
      return
    }
    potvrzovaciTlacitkoNode.disabled = true

    const telo = new FormData()
    telo.append('ajax', '1')
    telo.append('akce', 'odemknout-kontakty')
    telo.append('idAktivity', odemykanaAktivitaId)

    fetch(window.location.href, {method: 'POST', body: telo})
      .then(function (odpoved) {
        if (!odpoved.ok) {
          return odpoved.json().then(function (data) {
            throw new Error((data.errors && data.errors[0]) || 'Kontakty se nepodařilo zobrazit')
          })
        }
        window.location.reload()
      })
      .catch(function (chyba) {
        potvrzovaciTlacitkoNode.disabled = false
        window.alert(chyba.message)
      })
  })
})
