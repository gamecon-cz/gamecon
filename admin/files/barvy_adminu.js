// Světlé nebo tmavé barvy moderního vzhledu administrace (admin/templates/moderni/main.xtpl a moderní online
// prezence). Načítá se v hlavičce bez defer, aby třída moderni-tmave byla na <html> dřív, než se stránka vykreslí,
// jinak by při každém načtení probliklo světlé pozadí. Bez volby v patičce platí barvy zařízení.
(function () {
  'use strict'

  const html = document.documentElement
  const tmaveZarizeni = window.matchMedia('(prefers-color-scheme: dark)')
  const klicVolby = 'adminBarvy'
  const volby = ['svetle', 'tmave']
  // bez úložiště (soukromé okno, zakázaná data webu) platí volba jen do dalšího načtení stránky
  let volbaBezUloziste = ''

  // Pamatuje si to prohlížeč, ne účet, jako barvy zařízení: telefon může být tmavý a počítač světlý.
  function dejVolbu() {
    try {
      const volba = localStorage.getItem(klicVolby)
      return volby.includes(volba) ? volba : ''
    } catch (e) {
      return volbaBezUloziste
    }
  }

  function ulozVolbu(volba) {
    volbaBezUloziste = volba
    try {
      if (volba) {
        localStorage.setItem(klicVolby, volba)
      } else {
        localStorage.removeItem(klicVolby)
      }
    } catch (e) {
      // zůstane volbaBezUloziste
    }
  }

  function prebarvi() {
    const volba = dejVolbu()
    html.classList.toggle('moderni-tmave', volba === 'tmave' || (volba === '' && tmaveZarizeni.matches))
    document.querySelectorAll('[data-barvy-adminu]').forEach(function (tlacitko) {
      tlacitko.setAttribute('aria-pressed', String(tlacitko.dataset.barvyAdminu === volba))
    })
  }

  prebarvi()
  // Safari do verze 13 nezná addEventListener na MediaQueryList, jen starší addListener; výjimka by tu shodila
  // i přepínač barev níže.
  if (typeof tmaveZarizeni.addEventListener === 'function') {
    tmaveZarizeni.addEventListener('change', prebarvi)
  } else {
    tmaveZarizeni.addListener(prebarvi)
  }
  // volba z jiné záložky
  window.addEventListener('storage', function (event) {
    if (event.key === klicVolby || event.key === null) {
      prebarvi()
    }
  })
  // přepínač v patičce ještě v době načtení skriptu neexistuje
  document.addEventListener('DOMContentLoaded', prebarvi)
  document.addEventListener('click', function (event) {
    const tlacitko = event.target.closest('[data-barvy-adminu]')
    if (!tlacitko) {
      return
    }
    ulozVolbu(tlacitko.dataset.barvyAdminu)
    prebarvi()
  })
})()
