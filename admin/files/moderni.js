// Moderní vzhled administrace (admin/templates/moderni/main.xtpl): vysouvací menu na úzké obrazovce nebo když
// si ho uživatel schová, horní lišta, která při posunu dolů ustoupí (ať nezakrývá přilepené hlavičky tabulek),
// a vodorovně posuvné tabulky, které se do obsahu nevejdou.
(function () {
  'use strict'

  const html = document.documentElement
  const body = document.body
  const panel = document.getElementById('moderniPostranniPanel')
  const lista = document.querySelector('.moderni-horni-lista')
  const tlacitkoMenu = document.querySelector('.moderni-tlacitko-menu')
  const tlacitkoZavrit = document.querySelector('.moderni-tlacitko-zavrit')
  const tlacitkoSchovat = document.querySelector('.moderni-tlacitko-schovat')
  const tlacitkoPripnout = document.querySelector('.moderni-tlacitko-pripnout')
  const zavoj = document.querySelector('.moderni-zavoj')
  const sloupR = document.querySelector('.sloupR')
  const obsah = document.getElementById('mainContent')
  // stejná hranice jako v moderni.css
  const uzkaObrazovka = window.matchMedia('(max-width: 1023.98px)')
  // stejný klíč čte skript v hlavičce main.xtpl
  const klicSchovanehoMenu = 'adminSchovaneMenu'

  function jeMenuVysouvaci() {
    return uzkaObrazovka.matches || html.classList.contains('moderni-menu-schovane')
  }

  // Pamatuje si to prohlížeč, ne účet: na notebooku může být menu schované a na velkém monitoru ne.
  function nastavSchovaneMenu(schovat) {
    html.classList.toggle('moderni-menu-schovane', schovat)
    try {
      if (schovat) {
        localStorage.setItem(klicSchovanehoMenu, '1')
      } else {
        localStorage.removeItem(klicSchovanehoMenu)
      }
    } catch (e) {
      // bez úložiště platí jen do dalšího načtení stránky
    }
  }

  function jeMenuOtevrene() {
    return body.classList.contains('moderni-menu-otevrene')
  }

  function otevriMenu() {
    body.classList.add('moderni-menu-otevrene')
    tlacitkoMenu.setAttribute('aria-expanded', 'true')
    zavoj.hidden = false
    // obsah za závojem není dostupný ani z klávesnice
    sloupR.inert = true
    lista.inert = true
    // ne na pole hledání, to by na telefonu vysunulo klávesnici
    tlacitkoZavrit.focus()
  }

  function zavriMenu(vratitFokus) {
    if (!jeMenuOtevrene()) {
      return
    }
    body.classList.remove('moderni-menu-otevrene')
    tlacitkoMenu.setAttribute('aria-expanded', 'false')
    zavoj.hidden = true
    sloupR.inert = false
    lista.inert = false
    if (vratitFokus) {
      tlacitkoMenu.focus()
    }
  }

  if (panel && tlacitkoMenu && tlacitkoZavrit && tlacitkoSchovat && tlacitkoPripnout && zavoj && sloupR && lista) {
    tlacitkoMenu.addEventListener('click', otevriMenu)
    tlacitkoZavrit.addEventListener('click', function () {
      zavriMenu(true)
    })
    tlacitkoSchovat.addEventListener('click', function () {
      nastavSchovaneMenu(true)
      tlacitkoMenu.focus()
    })
    tlacitkoPripnout.addEventListener('click', function () {
      zavriMenu(false)
      nastavSchovaneMenu(false)
      tlacitkoSchovat.focus()
    })
    zavoj.addEventListener('click', function () {
      zavriMenu(true)
    })
    document.addEventListener('keydown', function (event) {
      // Esc v našeptávači jen zavře nabídku, ten si událost zruší
      if (event.key === 'Escape' && !event.defaultPrevented && jeMenuOtevrene()) {
        zavriMenu(true)
      }
    })
    uzkaObrazovka.addEventListener('change', function () {
      if (!jeMenuVysouvaci()) {
        zavriMenu(false)
      }
    })

    let posledniPosun = window.scrollY
    window.addEventListener('scroll', function () {
      const posun = window.scrollY
      if (Math.abs(posun - posledniPosun) < 8) {
        return
      }
      const skryt = jeMenuVysouvaci() && posun > posledniPosun && posun > lista.offsetHeight
      lista.classList.toggle('moderni-horni-lista-skryta', skryt)
      posledniPosun = posun
    }, {passive: true})
    lista.addEventListener('focusin', function () {
      lista.classList.remove('moderni-horni-lista-skryta')
    })

    // Výběr pracovního uživatele je ve vysouvacím menu, kam se fokus nedostane, dokud je zasunuté. Zkratka alt+U
    // (omnibox-1.1.5.js) i hlášky „Vyberte uživatele (pole vlevo)“ (onclick s focus()) proto menu nejdřív vysunou.
    const ukazVyberUzivatele = function () {
      const vyberUzivatele = document.getElementById('omniboxUzivateleProPraci')
      if (!vyberUzivatele || !jeMenuVysouvaci()) {
        return
      }
      if (!jeMenuOtevrene()) {
        otevriMenu()
      }
      vyberUzivatele.focus()
    }
    document.addEventListener('keydown', function (event) {
      if (event.altKey && !event.ctrlKey && !event.metaKey && event.key.toLowerCase() === 'u') {
        ukazVyberUzivatele()
      }
    })
    document.addEventListener('click', function (event) {
      if (event.target.closest('[onclick*="omniboxUzivateleProPraci"]')) {
        ukazVyberUzivatele()
      }
    })
  }

  function obalSirokeTabulky() {
    if (!obsah) {
      return
    }
    const pravyOkrajObsahu = obsah.getBoundingClientRect().right
    obsah.querySelectorAll('table').forEach(function (tabulka) {
      if (tabulka.parentElement.closest('table, .responzivni-tabulka, .moderni-posuvna-tabulka-posuvnik')) {
        return
      }
      if (tabulka.getBoundingClientRect().right <= pravyOkrajObsahu + 1) {
        return
      }
      const obal = document.createElement('div')
      obal.className = 'moderni-posuvna-tabulka'
      const posuvnik = document.createElement('div')
      posuvnik.className = 'moderni-posuvna-tabulka-posuvnik'
      tabulka.parentNode.insertBefore(obal, tabulka)
      obal.appendChild(posuvnik)
      posuvnik.appendChild(tabulka)
      const ukazPokracovani = function () {
        obal.classList.toggle('pokracuje-vlevo', posuvnik.scrollLeft > 1)
        obal.classList.toggle('pokracuje-vpravo', posuvnik.scrollLeft + posuvnik.clientWidth < posuvnik.scrollWidth - 1)
      }
      posuvnik.addEventListener('scroll', ukazPokracovani, {passive: true})
      window.addEventListener('resize', ukazPokracovani)
      ukazPokracovani()
    })
  }

  // Na telefonu je podmenu jeden posuvný řádek, aktivní položka má být vidět.
  function ukazAktivniPolozkuPodmenu() {
    const podmenu = document.getElementById('submenu')
    const aktivni = podmenu && podmenu.querySelector('.activeSubmenuLink')
    if (!aktivni || podmenu.scrollWidth <= podmenu.clientWidth) {
      return
    }
    const ramecPodmenu = podmenu.getBoundingClientRect()
    const ramecAktivni = aktivni.getBoundingClientRect()
    podmenu.scrollLeft += ramecAktivni.left - ramecPodmenu.left - (ramecPodmenu.width - ramecAktivni.width) / 2
  }

  obalSirokeTabulky()
  ukazAktivniPolozkuPodmenu()
  window.addEventListener('load', obalSirokeTabulky)
  let casovacZmenyVelikosti = null
  window.addEventListener('resize', function () {
    clearTimeout(casovacZmenyVelikosti)
    casovacZmenyVelikosti = setTimeout(obalSirokeTabulky, 200)
  })
})()
