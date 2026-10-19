/**
 * E-mail subject and text editor: symbol suggestions after typing `%`, the Insert symbol button,
 * live checking while typing, the Edit / Preview switch, sending a test e-mail and the question before leaving
 * with unsaved changes.
 *
 * Nobody writes the symbol list by hand, it comes from the server in <script type="application/json">.
 * The server always decides, the check here is only a convenience.
 *
 * @see \Gamecon\Kanaly\EmailovaSablona\EditorSablonyHtml
 * @see editor-sablony-logika.js
 */
(function () {
  'use strict'

  const logika = window.EditorSablonyLogika

  document.querySelectorAll('.editorSablony').forEach(function (koren) {
    new EditorSablony(koren)
  })

  function EditorSablony(koren) {
    const formular = koren.querySelector('.editorSablony_formular')
    const predmetPole = koren.querySelector('.editorSablony_predmet')
    const textPole = koren.querySelector('.editorSablony_text')
    const hlasky = koren.querySelector('.editorSablony_hlaskyKontroly')
    const hlaskyServeru = koren.querySelector('.editorSablony_hlaskyServeru')
    const tlacitkoUlozit = koren.querySelector('.editorSablony_ulozit')
    const tlacitkoVlozit = koren.querySelector('.editorSablony_vlozit')
    const dialogSymbolu = koren.querySelector('.editorSablony_symboly')
    const nahledOkno = koren.querySelector('.editorSablony_nahled')
    const prepinace = koren.querySelectorAll('.editorSablony_rezim')
    const naseptavac = koren.querySelector('.editorSablony_naseptavac')
    // Present only on a page that offers sending a test e-mail
    const tlacitkoTestu = koren.querySelector('.editorSablony_test')
    const dialogTestu = koren.querySelector('.editorSablony_testDialog')
    const stavTestu = koren.querySelector('.editorSablony_testStav')
    // Present only on an environment that catches e-mails instead of delivering them
    const tlacitkoSchranky = koren.querySelector('.editorSablony_schranka')
    /** @type {{promenne: object[], systemove: object[], neverejne: string[]}} */
    const data = JSON.parse(koren.querySelector('.editorSablony_data').textContent)

    let aktivniPole = textPole
    let naseptavaneSymboly = []
    let vybranaPolozka = 0
    let rozepsanySymbol = null
    let casovacKontroly = null
    let pocetChyb = 0
    let testSeOdesila = false

    function symbolyProPole(pole) {
      const jePredmet = pole === predmetPole
      const promenne = data.promenne
        .filter(function (promenna) {
          return !jePredmet || promenna.vPredmetu
        })
        .map(function (promenna) {
          return {nazev: promenna.nazev, popis: promenna.popis, ukazka: promenna.ukazka}
        })

      return promenne.concat(data.systemove)
    }

    // --- live checking while typing

    function zkontroluj() {
      const chyby = logika.validuj(data, predmetPole.value, textPole.value)
      // Server errors would now only repeat what the check shows, or are no longer current
      if (hlaskyServeru && (chyby.length > 0 || hlasky.dataset.zkontrolovano)) {
        hlaskyServeru.remove()
      }
      hlasky.dataset.zkontrolovano = '1'
      hlasky.textContent = ''
      if (chyby.length > 0) {
        const seznam = document.createElement('ul')
        chyby.forEach(function (chyba) {
          const polozka = document.createElement('li')
          polozka.textContent = chyba
          seznam.appendChild(polozka)
        })
        hlasky.appendChild(seznam)
      }
      predmetPole.classList.toggle('editorSablony_chybne', chyby.some(function (chyba) {
        return chyba.indexOf('Předmět') === 0
      }))
      textPole.classList.toggle('editorSablony_chybne', chyby.some(function (chyba) {
        return chyba.indexOf('Text') === 0
      }))
      tlacitkoUlozit.disabled = chyby.length > 0
      pocetChyb = chyby.length
      srovnejTlacitkoTestu()

      return chyby
    }

    function zkontrolujPozdeji() {
      clearTimeout(casovacKontroly)
      casovacKontroly = setTimeout(zkontroluj, 150)
    }

    // --- inserting

    /**
     * execCommand records the text as ordinary typing, so Ctrl+Z undoes an inserted symbol. setRangeText is a fallback
     * for a browser that rejects execCommand.
     */
    function vloz(pole, zacatek, konec, text) {
      pole.focus()
      pole.setSelectionRange(zacatek, konec)
      let vlozeno = false
      try {
        vlozeno = document.execCommand('insertText', false, text)
      } catch (chyba) {
        vlozeno = false
      }
      if (!vlozeno) {
        pole.setRangeText(text, zacatek, konec, 'end')
        pole.dispatchEvent(new Event('input', {bubbles: true}))
      }
      zvyrazniVlozeny(pole, zacatek, text)
    }

    // --- highlight of the inserted symbol

    // Equal to the duration of the animation of .editorSablony_vlozeno in editor-sablony.css
    const TRVANI_ZVYRAZNENI_MS = 3000
    let zvyrazneni = null

    function zrusZvyrazneni() {
      if (!zvyrazneni) {
        return
      }
      clearTimeout(zvyrazneni.casovac)
      zvyrazneni.kontejner.remove()
      zvyrazneni = null
    }

    /**
     * The text of a field cannot be styled in part, so a coloured box is laid over the place where the symbol landed.
     * The box sits in the offset parent of the field, so it moves with the page, and is clipped to the field.
     */
    function zvyrazniVlozeny(pole, zacatek, text) {
      zrusZvyrazneni()
      const kontejner = document.createElement('div')
      kontejner.className = 'editorSablony_zvyrazneni'
      kontejner.setAttribute('aria-hidden', 'true')
      const znacka = document.createElement('div')
      znacka.className = 'editorSablony_vlozeno'
      kontejner.appendChild(znacka)
      ;(pole.offsetParent || document.body).appendChild(kontejner)
      zvyrazneni = {
        pole: pole,
        zacatek: zacatek,
        text: text,
        kontejner: kontejner,
        znacka: znacka,
        casovac: setTimeout(zrusZvyrazneni, TRVANI_ZVYRAZNENI_MS),
      }
      umistiZvyrazneni()
    }

    function umistiZvyrazneni() {
      if (!zvyrazneni) {
        return
      }
      const pole = zvyrazneni.pole
      // An edit before the symbol moved it, or the field is hidden: the box would mark the wrong place
      if (pole.hidden || pole.value.substr(zvyrazneni.zacatek, zvyrazneni.text.length) !== zvyrazneni.text) {
        zrusZvyrazneni()
        return
      }
      const styl = window.getComputedStyle(pole)
      const kontejner = zvyrazneni.kontejner.style
      kontejner.left = (pole.offsetLeft + (parseFloat(styl.borderLeftWidth) || 0)) + 'px'
      kontejner.top = (pole.offsetTop + (parseFloat(styl.borderTopWidth) || 0)) + 'px'
      kontejner.width = pole.clientWidth + 'px'
      kontejner.height = pole.clientHeight + 'px'
      const obdelnik = obdelnikTextu(pole, zvyrazneni.zacatek, zvyrazneni.zacatek + zvyrazneni.text.length)
      const znacka = zvyrazneni.znacka.style
      znacka.left = obdelnik.left + 'px'
      znacka.top = obdelnik.top + 'px'
      znacka.width = obdelnik.sirka + 'px'
      znacka.height = obdelnik.vyska + 'px'
    }

    ;[predmetPole, textPole].forEach(function (pole) {
      pole.addEventListener('input', umistiZvyrazneni)
      pole.addEventListener('scroll', umistiZvyrazneni)
    })
    window.addEventListener('resize', umistiZvyrazneni)
    // The text can move without any of these events: a field resized by its drag handle, a message above it appearing or going away
    if (window.ResizeObserver) {
      const sledovatelVelikosti = new ResizeObserver(umistiZvyrazneni)
      ;[koren, predmetPole, textPole].forEach(function (uzel) {
        sledovatelVelikosti.observe(uzel)
      })
    }

    function vlozSymbolNaKurzor(pole, nazevSymbolu) {
      const rozsah = logika.rozsahKPrepsani(pole.value, pole.selectionStart, pole.selectionEnd)
      vloz(pole, rozsah.zacatek, rozsah.konec, '%' + nazevSymbolu + '%')
    }

    // --- suggestions

    /** A hidden copy of the field that lays out its text the same way, to measure where a place of the text lands */
    function zrcadloPole(pole) {
      const styl = window.getComputedStyle(pole)
      const zrcadlo = document.createElement('div')
      ;['boxSizing', 'width', 'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth',
        'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'fontFamily', 'fontSize', 'fontWeight',
        'fontStyle', 'letterSpacing', 'textTransform', 'wordSpacing', 'textIndent', 'textAlign', 'lineHeight', 'tabSize',
      ].forEach(function (vlastnost) {
        zrcadlo.style[vlastnost] = styl[vlastnost]
      })
      zrcadlo.style.position = 'absolute'
      zrcadlo.style.visibility = 'hidden'
      zrcadlo.style.top = '0'
      zrcadlo.style.left = '-9999px'
      zrcadlo.style.whiteSpace = pole.tagName === 'TEXTAREA' ? 'pre-wrap' : 'pre'
      zrcadlo.style.wordWrap = 'break-word'
      if (pole.tagName === 'TEXTAREA') {
        // A classic scrollbar takes width from the text, and the copy has none: without this it wraps lines later than the field
        zrcadlo.style.boxSizing = 'content-box'
        zrcadlo.style.width = Math.max(0, pole.clientWidth - parseFloat(styl.paddingLeft) - parseFloat(styl.paddingRight)) + 'px'
      }

      return zrcadlo
    }

    function souradniceKurzoru(pole, pozice) {
      const styl = window.getComputedStyle(pole)
      const zrcadlo = zrcadloPole(pole)
      zrcadlo.textContent = pole.value.substring(0, pozice)
      const znacka = document.createElement('span')
      znacka.textContent = pole.value.substring(pozice) || '.'
      zrcadlo.appendChild(znacka)
      document.body.appendChild(zrcadlo)
      const vysledek = {
        left: znacka.offsetLeft - pole.scrollLeft,
        top: znacka.offsetTop - pole.scrollTop,
        vyska: parseFloat(styl.lineHeight) || parseFloat(styl.fontSize) * 1.3,
      }
      document.body.removeChild(zrcadlo)

      return vysledek
    }

    /** @return {{left: number, top: number, sirka: number, vyska: number}} relative to the padding box of the field */
    function obdelnikTextu(pole, zacatek, konec) {
      const zrcadlo = zrcadloPole(pole)
      zrcadlo.textContent = pole.value.substring(0, zacatek)
      const znacka = document.createElement('span')
      znacka.textContent = pole.value.substring(zacatek, konec)
      zrcadlo.appendChild(znacka)
      zrcadlo.appendChild(document.createTextNode(pole.value.substring(konec)))
      document.body.appendChild(zrcadlo)
      const vysledek = {
        left: znacka.offsetLeft - pole.scrollLeft,
        top: znacka.offsetTop - pole.scrollTop,
        sirka: znacka.offsetWidth,
        vyska: znacka.offsetHeight,
      }
      document.body.removeChild(zrcadlo)

      return vysledek
    }

    function skryjNaseptavac() {
      naseptavac.hidden = true
      naseptavaneSymboly = []
      rozepsanySymbol = null
    }

    function vykresliNaseptavac() {
      naseptavac.textContent = ''
      naseptavaneSymboly.forEach(function (symbol, index) {
        const polozka = document.createElement('div')
        polozka.className = 'editorSablony_polozka' + (index === vybranaPolozka ? ' editorSablony_vybrana' : '')
        polozka.setAttribute('role', 'option')
        const nazev = document.createElement('code')
        nazev.textContent = '%' + symbol.nazev + '%'
        const popis = document.createElement('span')
        popis.textContent = symbol.popis
        polozka.appendChild(nazev)
        polozka.appendChild(popis)
        polozka.addEventListener('mousedown', function (udalost) {
          udalost.preventDefault() // the field must not lose focus
          vlozZNaseptavace(index)
        })
        naseptavac.appendChild(polozka)
      })
    }

    function otevriNaseptavac(pole) {
      if (pole.selectionStart !== pole.selectionEnd) {
        skryjNaseptavac()
        return
      }
      const nalezeny = logika.najdiRozepsanySymbol(pole.value, pole.selectionStart)
      if (!nalezeny) {
        skryjNaseptavac()
        return
      }
      const symboly = logika.filtrujSymboly(symbolyProPole(pole), nalezeny.predpona)
      if (symboly.length === 0) {
        skryjNaseptavac()
        return
      }

      rozepsanySymbol = nalezeny
      naseptavaneSymboly = symboly
      vybranaPolozka = 0
      vykresliNaseptavac()

      const ramecPole = pole.getBoundingClientRect()
      const kurzor = souradniceKurzoru(pole, pole.selectionStart)
      naseptavac.style.left = Math.max(0, ramecPole.left + kurzor.left) + 'px'
      naseptavac.style.top = (ramecPole.top + kurzor.top + kurzor.vyska) + 'px'
      naseptavac.hidden = false
    }

    function vlozZNaseptavace(index) {
      const symbol = naseptavaneSymboly[index]
      if (!symbol || !rozepsanySymbol) {
        return
      }
      const pole = aktivniPole
      const zacatek = rozepsanySymbol.zacatek
      const konec = pole.selectionStart
      skryjNaseptavac()
      vloz(pole, zacatek, konec, '%' + symbol.nazev + '%')
    }

    function obsluzKlavesyNaseptavace(udalost) {
      if (naseptavac.hidden) {
        return
      }
      switch (udalost.key) {
        case 'ArrowDown':
          vybranaPolozka = (vybranaPolozka + 1) % naseptavaneSymboly.length
          break
        case 'ArrowUp':
          vybranaPolozka = (vybranaPolozka - 1 + naseptavaneSymboly.length) % naseptavaneSymboly.length
          break
        case 'Enter':
        case 'Tab':
          udalost.preventDefault()
          vlozZNaseptavace(vybranaPolozka)
          return
        case 'Escape':
          udalost.preventDefault()
          skryjNaseptavac()
          return
        default:
          return
      }
      udalost.preventDefault()
      vykresliNaseptavac()
      const vybrana = naseptavac.querySelector('.editorSablony_vybrana')
      if (vybrana) {
        vybrana.scrollIntoView({block: 'nearest'})
      }
    }

    ;[predmetPole, textPole].forEach(function (pole) {
      pole.addEventListener('focus', function () {
        aktivniPole = pole
      })
      pole.addEventListener('input', function (udalost) {
        zkontrolujPozdeji()
        odesilaSe = false
        srovnejVarovaniPredOdchodem()
        // Undo, paste and drag-and-drop are not typing: the suggestions would open over text the author was not
        // currently typing, and the next Enter would insert a symbol instead of a new line.
        if (/^(history|insertFromPaste|insertFromDrop)/.test(udalost.inputType || '')) {
          skryjNaseptavac()
          return
        }
        otevriNaseptavac(pole)
      })
      pole.addEventListener('keydown', obsluzKlavesyNaseptavace)
      pole.addEventListener('click', function () {
        otevriNaseptavac(pole)
      })
      pole.addEventListener('blur', skryjNaseptavac)
    })
    // Scrolling the page would leave the suggestions hanging in the old place. The field and the suggestions
    // scroll while typing or browsing, and must stay open then.
    window.addEventListener('scroll', function (udalost) {
      if (udalost.target !== predmetPole && udalost.target !== textPole && !naseptavac.contains(udalost.target)) {
        skryjNaseptavac()
      }
    }, true)
    window.addEventListener('resize', skryjNaseptavac)

    // --- Insert symbol button

    function vykresliSeznamSymbolu(pole) {
      const telo = dialogSymbolu.querySelector('tbody')
      telo.textContent = ''
      symbolyProPole(pole).forEach(function (symbol) {
        const radek = document.createElement('tr')
        radek.className = 'editorSablony_radekSymbolu'
        ;[
          {text: '%' + symbol.nazev + '%', kod: true},
          {text: symbol.popis, kod: false},
          {text: symbol.ukazka, kod: false},
        ].forEach(function (bunka) {
          const td = document.createElement('td')
          if (bunka.kod) {
            const kod = document.createElement('code')
            kod.textContent = bunka.text
            td.appendChild(kod)
          } else {
            td.textContent = bunka.text
          }
          radek.appendChild(td)
        })
        radek.addEventListener('click', function () {
          dialogSymbolu.close()
          vlozSymbolNaKurzor(pole, symbol.nazev)
        })
        telo.appendChild(radek)
      })
      dialogSymbolu.querySelector('.editorSablony_kam').textContent = pole === predmetPole
        ? 'do předmětu (jen vybrané symboly)'
        : 'do textu'
    }

    tlacitkoVlozit.addEventListener('click', function () {
      skryjNaseptavac()
      vykresliSeznamSymbolu(aktivniPole)
      dialogSymbolu.showModal()
    })
    dialogSymbolu.querySelector('.editorSablony_zavritSymboly').addEventListener('click', function () {
      dialogSymbolu.close()
    })
    // a click on the dimmed backdrop targets the <dialog> itself, not anything inside
    dialogSymbolu.addEventListener('click', function (udalost) {
      if (udalost.target === dialogSymbolu) {
        dialogSymbolu.close()
      }
    })

    // --- test e-mail

    function srovnejTlacitkoTestu() {
      if (tlacitkoTestu) {
        tlacitkoTestu.disabled = pocetChyb > 0 || testSeOdesila
      }
    }

    function ukazStavTestu(text, jeChyba) {
      stavTestu.hidden = false
      stavTestu.textContent = text
      stavTestu.classList.toggle('editorSablony_testStavChyba', jeChyba)
    }

    // Moments of switching on and off in turn: two flashes in one second
    const OKAMZIKY_BLIKANI_SCHRANKY_MS = [0, 350, 650, 1000]
    let casovaceBlikaniSchranky = []

    /** Draws the eye to the mailbox once the test e-mail has landed in it; a sequence still running starts over */
    function zablikejSchranku() {
      if (!tlacitkoSchranky) {
        return
      }
      casovaceBlikaniSchranky.forEach(clearTimeout)
      casovaceBlikaniSchranky = OKAMZIKY_BLIKANI_SCHRANKY_MS.map(function (okamzik, poradi) {
        return setTimeout(function () {
          tlacitkoSchranky.classList.toggle('editorSablony_schrankaBlika', poradi % 2 === 0)
        }, okamzik)
      })
    }

    function odesliTest() {
      const udaje = new FormData()
      udaje.append('predmet', predmetPole.value)
      udaje.append('text', textPole.value)
      testSeOdesila = true
      srovnejTlacitkoTestu()
      ukazStavTestu('Odesílám…', false)

      fetch(koren.dataset.testUrl, {method: 'POST', body: udaje, credentials: 'same-origin'})
        .then(function (odpoved) {
          if (!odpoved.ok) {
            throw new Error('HTTP ' + odpoved.status)
          }
          return odpoved.text()
        })
        .then(function (telo) {
          let vysledek = null
          try {
            vysledek = JSON.parse(telo)
          } catch (chyba) {
            vysledek = null
          }
          // An expired session returns 200 with the login page, not the answer
          if (vysledek === null || typeof vysledek.ok !== 'boolean') {
            ukazStavTestu('Test se nepodařilo odeslat, nejspíš vypršelo přihlášení. Otevři admin v jiné záložce,'
              + ' přihlas se a zkus to znovu - text v editoru zůstává.', true)
            return
          }
          ukazStavTestu(vysledek.zprava, !vysledek.ok)
          if (vysledek.ok) {
            zablikejSchranku()
          }
        })
        .catch(function (chyba) {
          ukazStavTestu('Test se nepodařilo odeslat: ' + chyba.message, true)
        })
        .then(function () {
          testSeOdesila = false
          srovnejTlacitkoTestu()
        })
    }

    if (tlacitkoTestu && dialogTestu && stavTestu) {
      tlacitkoTestu.addEventListener('click', function () {
        skryjNaseptavac()
        dialogTestu.showModal()
      })
      dialogTestu.querySelector('.editorSablony_testZrusit').addEventListener('click', function () {
        dialogTestu.close()
      })
      dialogTestu.querySelector('.editorSablony_testOdeslat').addEventListener('click', function () {
        dialogTestu.close()
        odesliTest()
      })
      // a click on the dimmed backdrop targets the <dialog> itself, not anything inside
      dialogTestu.addEventListener('click', function (udalost) {
        if (udalost.target === dialogTestu) {
          dialogTestu.close()
        }
      })
    }

    if (tlacitkoSchranky) {
      tlacitkoSchranky.addEventListener('click', function () {
        window.open(tlacitkoSchranky.dataset.url, '_blank', 'noopener')
      })
    }

    // --- Edit / Preview switch

    function nastavRezim(rezim) {
      prepinace.forEach(function (prepinac) {
        prepinac.classList.toggle('editorSablony_aktivniRezim', prepinac.dataset.rezim === rezim)
      })
      const nahled = rezim === 'nahled'
      if (nahled) {
        zrusZvyrazneni()
      }
      textPole.hidden = nahled
      tlacitkoVlozit.disabled = nahled
      nahledOkno.hidden = !nahled
      if (!nahled) {
        textPole.focus()
        return
      }
      nactiNahled()
    }

    function nactiNahled() {
      nahledOkno.textContent = 'Načítám…'
      const udaje = new FormData()
      udaje.append('predmet', predmetPole.value)
      udaje.append('text', textPole.value)

      fetch(koren.dataset.nahledUrl, {method: 'POST', body: udaje, credentials: 'same-origin'})
        .then(function (odpoved) {
          if (!odpoved.ok) {
            throw new Error('HTTP ' + odpoved.status)
          }
          return odpoved.text()
        })
        .then(function (html) {
          // An expired session returns 200 with the login page. We do not put it into the frame: it would offer a login
          // form to type a password into, and the text being edited would vanish.
          if (html.indexOf('data-editor-sablony-nahled') === -1) {
            nahledOkno.textContent = 'Náhled se nepodařilo načíst, nejspíš vypršelo přihlášení. Otevři admin v jiné záložce,'
              + ' přihlas se a zkus to znovu - rozepsaný text tu zůstává.'
            return
          }
          nahledOkno.innerHTML = html
        })
        .catch(function (chyba) {
          nahledOkno.textContent = 'Náhled se nepodařilo načíst: ' + chyba.message
        })
    }

    prepinace.forEach(function (prepinac) {
      prepinac.addEventListener('click', function () {
        nastavRezim(prepinac.dataset.rezim)
      })
    })

    // --- leaving with unsaved changes

    // The defaults are what the server rendered; the current values may already be refilled by the browser from before
    // the page was left (Firefox before this script runs, Chrome after it and without an input event).
    // A save that failed on the server leaves the typed text only in this form, even though it looks like the saved one.
    const ulozeniSelhalo = hlaskyServeru !== null
    let odesilaSe = false

    function maNeulozeneZmeny() {
      return ulozeniSelhalo
        || predmetPole.value !== predmetPole.defaultValue
        || textPole.value !== textPole.defaultValue
    }

    function zeptejSePredOdchodem(udalost) {
      udalost.preventDefault()
      udalost.returnValue = true // Chrome and Edge before 119 ask only when this is set too
    }

    // Registered only while there is something to lose: Firefox does not keep a page with this listener in its back/forward cache.
    function srovnejVarovaniPredOdchodem() {
      if (maNeulozeneZmeny() && !odesilaSe) {
        window.addEventListener('beforeunload', zeptejSePredOdchodem)
      } else {
        window.removeEventListener('beforeunload', zeptejSePredOdchodem)
      }
    }

    formular.addEventListener('submit', function (udalost) {
      if (zkontroluj().length > 0) {
        udalost.preventDefault()
        return
      }
      odesilaSe = true
      srovnejVarovaniPredOdchodem()
    })

    // Chrome refills the form on Back only after the scripts have run, and fires no event for it
    window.addEventListener('pageshow', srovnejVarovaniPredOdchodem)

    srovnejVarovaniPredOdchodem()
    zkontroluj()
  }
}())
