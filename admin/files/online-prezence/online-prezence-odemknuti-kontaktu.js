/**
 * Souhlas se zobrazením kontaktů účastníků (e-maily, telefony).
 *
 * Rozhodnutí drží server v session, ne cookie ani localStorage – jinak by si
 * odemčení nastavil i ten, kdo souhlas odklikat nechce, a do logu by se to
 * nepropsalo.
 *
 * Kontakty se do HTML vůbec nedostanou, dokud není potvrzeno, takže je nejde
 * jen odkrýt v prohlížeči – server musí řádky překreslit. Odpověď na potvrzení
 * proto rovnou nese hotové HTML účastníků a klient jen vymění obsah tabulky.
 * Reload stránky by uživatele odscrolloval pryč od aktivity, kterou zrovna řeší.
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

  /**
   * @param {string|number} idAktivity
   * @param {Array<{id_uzivatele: number, html_ucastnika: string}>} ucastnici
   * @param {Array<string>} emaily
   */
  function vymenRadkyUcastniku(idAktivity, ucastnici, emaily) {
    const aktivitaNode = document.getElementById(`aktivita-${idAktivity}`)
    if (!aktivitaNode) {
      return
    }

    const ucastniciSeznam = aktivitaNode.querySelector('.ucastnici-seznam')
    if (ucastniciSeznam && ucastnici) {
      ucastniciSeznam.innerHTML = ''
      ucastnici.forEach(function (ucastnikData) {
        const htmlUcastnika = (ucastnikData.html_ucastnika || '').trim()
        if (htmlUcastnika === '') {
          return
        }
        const template = document.createElement('template')
        template.innerHTML = htmlUcastnika
        ucastniciSeznam.appendChild(template.content.firstChild)
      })
    }

    // hromadný mailto odkaz je do potvrzení schovaný, teď ho odkryjeme a naplníme
    const emailyNode = document.getElementById(`emaily-${idAktivity}`)
    if (emailyNode) {
      emailyNode.classList.remove('display-none')
      const emailyAnchor = emailyNode.querySelector('a')
      if (emailyAnchor && emaily) {
        emailyAnchor.href = 'mailto:?bcc=' + emaily.join(',')
        emailyAnchor.innerText = emaily.join(', ')
      }
    }

    const zamykaciPruhNode = document.getElementById(`kontakty-zamcene-${idAktivity}`)
    if (zamykaciPruhNode) {
      zamykaciPruhNode.remove()
    }

    // stejná konvence jako u načtení počátečního stavu – naváže tooltipy,
    // hlídání změn a ukazatele zaplněnosti na nově vložené řádky
    document.dispatchEvent(new CustomEvent('aktivitaVyrenderovana', {detail: aktivitaNode}))
  }

  potvrzovaciTlacitkoNode.addEventListener('click', function () {
    if (!checkboxNode.checked || !odemykanaAktivitaId) {
      return
    }
    const odemykanaAktivitaIdProTentoKlik = odemykanaAktivitaId
    potvrzovaciTlacitkoNode.disabled = true

    const telo = new FormData()
    telo.append('ajax', '1')
    telo.append('akce', 'odemknout-kontakty')
    telo.append('idAktivity', odemykanaAktivitaIdProTentoKlik)

    fetch(window.location.href, {method: 'POST', body: telo})
      .then(function (odpoved) {
        return odpoved.json().then(function (data) {
          if (!odpoved.ok) {
            throw new Error((data.errors && data.errors[0]) || 'Kontakty se nepodařilo zobrazit')
          }
          return data
        })
      })
      .then(function (data) {
        vymenRadkyUcastniku(odemykanaAktivitaIdProTentoKlik, data.ucastnici, data.emaily)
        jQuery(modalNode).modal('hide')
      })
      .catch(function (chyba) {
        potvrzovaciTlacitkoNode.disabled = false
        window.alert(chyba.message)
      })
  })
})
