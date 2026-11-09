// Moderní vzhled administrace (admin/templates/moderni/main.xtpl): vysouvací menu na úzké obrazovce nebo když
// si ho uživatel schová (na širší obrazovce s myší z něj zůstane úzký panel s ikonami), horní lišta, která při
// posunu dolů ustoupí (ať nezakrývá přilepené hlavičky tabulek), zhuštěný panel, když se na výšku nevejde,
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
  // schované menu je tu úzký panel, který se rozbalí najetím myší (moderni.css, Úzký panel)
  const obrazovkaProUzkyPanel = window.matchMedia('(min-width: 1024px) and (hover: hover) and (pointer: fine)')
  // stejný klíč čte skript v hlavičce main.xtpl
  const klicSchovanehoMenu = 'adminSchovaneMenu'
  // Posuvné tabulky ukazují stín u okraje, kudy pokračují. Po změně velikosti okna je přepočítá jeden společný
  // posluchač, ne každá tabulka svůj.
  const ukazateleOkrajuTabulek = []

  function jePanelUzky() {
    return html.classList.contains('moderni-menu-schovane') && obrazovkaProUzkyPanel.matches
  }

  function jeMenuVysouvaci() {
    return uzkaObrazovka.matches || (html.classList.contains('moderni-menu-schovane') && !jePanelUzky())
  }

  // Po kliknutí na Schovat nebo po Esc je myš ještě nad panelem a ten by se hned zase rozbalil. Najetím se
  // rozbalí, až z něj myš odjede (posluchač mouseleave níž).
  function nerozbalujPanelDokudMysNeodjede() {
    panel.classList.add('moderni-panel-nerozbalovat')
  }

  // Esc sbalí úzký panel rozbalený přes obsah, ať je obsah pod ním vidět. Fokus z panelu přejde na obsah, jako
  // se po zavření vysouvacího menu vrátí na tlačítko menu: ve sbaleném panelu by pole nebo tlačítko s fokusem
  // nebylo vidět.
  function sbalUzkyPanel() {
    if (panel.contains(document.activeElement)) {
      obsah.focus({preventScroll: true})
    }
    if (panel.matches(':hover')) {
      nerozbalujPanelDokudMysNeodjede()
    }
  }

  // Safari do verze 13 nezná u MediaQueryList addEventListener, jen addListener.
  function priZmeneObrazovky(dotaz, posluchac) {
    if (dotaz.addEventListener) {
      dotaz.addEventListener('change', posluchac)
    } else {
      dotaz.addListener(posluchac)
    }
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

  if (panel && tlacitkoMenu && tlacitkoZavrit && tlacitkoSchovat && tlacitkoPripnout && zavoj && sloupR && lista
    && obsah) {
    tlacitkoMenu.addEventListener('click', otevriMenu)
    tlacitkoZavrit.addEventListener('click', function () {
      zavriMenu(true)
    })
    tlacitkoSchovat.addEventListener('click', function (event) {
      nastavSchovaneMenu(true)
      if (jeMenuVysouvaci()) {
        tlacitkoMenu.focus()
      } else if (event.detail === 0) {
        // Z klávesnice zůstane fokus v úzkém panelu, který je proto rozbalený, dokud z něj fokus neodejde.
        // Po kliknutí myší ne: tlačítko Schovat zmizí a panel se sbalí.
        tlacitkoPripnout.focus()
      } else {
        nerozbalujPanelDokudMysNeodjede()
      }
    })
    tlacitkoPripnout.addEventListener('click', function () {
      zavriMenu(false)
      nastavSchovaneMenu(false)
      panel.classList.remove('moderni-panel-nerozbalovat')
      tlacitkoSchovat.focus()
    })
    zavoj.addEventListener('click', function () {
      zavriMenu(true)
    })
    panel.addEventListener('mouseleave', function () {
      panel.classList.remove('moderni-panel-nerozbalovat')
    })
    document.addEventListener('keydown', function (event) {
      // Esc v našeptávači jen zavře nabídku, ten si událost zruší
      if (event.key !== 'Escape' || event.defaultPrevented) {
        return
      }
      if (jeMenuOtevrene()) {
        zavriMenu(true)
      } else if (jePanelUzky()) {
        sbalUzkyPanel()
      }
    })
    // Když menu přestane být vysouvací, vysunuté se zavře: po rozšíření okna, ale i po připojení myši k tabletu
    // nebo přepnutí notebooku z dotyku, kdy se velikost okna nezmění a schované menu se stane úzkým panelem.
    const zavriMenuPokudNeniVysouvaci = function () {
      if (!jeMenuVysouvaci()) {
        zavriMenu(false)
      }
    }
    priZmeneObrazovky(uzkaObrazovka, zavriMenuPokudNeniVysouvaci)
    priZmeneObrazovky(obrazovkaProUzkyPanel, zavriMenuPokudNeniVysouvaci)

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
    // (omnibox.js) i hlášky „Vyberte uživatele (pole vlevo)“ (onclick s focus()) proto menu nejdřív vysunou.
    // Úzký panel se rozbalí fokusem do pole sám.
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
    // Přes jquery.hotkeys jako v omnibox.js: pozná klávesu podle kódu, ne podle znaku (alt+U je na Macu a iPadu
    // mrtvá klávesa pro přehlásku, event.key je tam 'Dead'), a při psaní do formulářového pole nic nedělá.
    jQuery(document).on('keydown', null, 'alt+u', function () {
      ukazVyberUzivatele()
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
      ukazateleOkrajuTabulek.push(ukazPokracovani)
      ukazPokracovani()
    })
  }

  // Panel, který se na výšku nevejde, se zhustí (moderni.css, Kompaktní panel). Při načtení to rozhodne skript
  // hned za panelem v main.xtpl, tady jen po změně velikosti okna. Měří se plný panel a případné zhuštění
  // proběhne dřív, než se stránka překreslí.
  function zhustiPanelPokudSeNevejde() {
    if (!panel) {
      return
    }
    html.classList.remove('moderni-panel-kompaktni')
    html.classList.toggle('moderni-panel-kompaktni', panel.scrollHeight > panel.clientHeight)
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
    // stín u okraje hned, i během tažení okna nebo otáčení tabletu; jen přepne třídy
    ukazateleOkrajuTabulek.forEach(function (ukazPokracovani) {
      ukazPokracovani()
    })
    // měření celé stránky až po usazení velikosti
    clearTimeout(casovacZmenyVelikosti)
    casovacZmenyVelikosti = setTimeout(function () {
      obalSirokeTabulky()
      zhustiPanelPokudSeNevejde()
    }, 200)
  })
})()
