/**
 * Bingo z jednání rady (admin/scripts/modules/bingo.php).
 * Odškrtnutí pošle hned a překreslí kartu podle odpovědi. Stav soutěže (start, co kdo odškrtl, vítěz) si načítá tak
 * často, jak řekne server (`sledovatZa`: za běhu soutěže každé 3 s), takže se změny od ostatních objeví bez obnovení
 * stránky a vítěze uvidí všichni.
 */
(function () {
  'use strict'

  // potvrzení tlačítek s data-potvrzeni (spuštění soutěže, výměna karty, mazání)
  document.addEventListener('submit', function (event) {
    const potvrzeni = event.submitter && event.submitter.dataset.potvrzeni
    if (potvrzeni && !window.confirm(potvrzeni)) {
      event.preventDefault()
    }
  })

  const hra = document.querySelector('.bingo-hra')
  if (!hra) {
    return
  }

  const urlStavu = hra.dataset.urlStavu
  const urlAkci = hra.dataset.urlAkci
  const idJednani = hra.dataset.idJednani
  const idUzivatele = Number(hra.dataset.idUzivatele)
  /** karta, kterou stránka vykreslila; null = bez karty */
  const idKarty = hra.dataset.idKarty === '' ? null : Number(hra.dataset.idKarty)
  const volnePole = Number(hra.dataset.volnePole)
  const rady = JSON.parse(hra.dataset.rady)
  const velikostKarty = rady[0].length

  const popis = hra.querySelector('.bingo-popis')
  const spusteni = hra.querySelector('.bingo-spusteni')
  const karta = hra.querySelector('form.bingo-karta')
  const generovani = hra.querySelector('.bingo-generovani')
  const ostatni = hra.querySelector('.bingo-ostatni')
  const ostatniSeznam = hra.querySelector('.bingo-ostatni-seznam')
  const vitezove = hra.querySelector('.bingo-vitezove')
  const vitezoveKarty = hra.querySelector('.bingo-vitezove-karty')
  const vyhlaseni = hra.querySelector('.bingo-vyhlaseni')

  /** pozice na kartě => id fráze */
  const frazeNaKarte = new Map()
  if (karta) {
    karta.querySelectorAll('[data-id-fraze]').forEach(function (pole) {
      frazeNaKarte.set(Number(pole.dataset.pozice), pole.dataset.idFraze)
    })
  }
  const idsNaKarte = new Set(frazeNaKarte.values())

  let stav = JSON.parse(hra.dataset.stav)
  /** id frází, kterým jde teď zrušit odškrtnutí (po bingu jen z vítězných řad) */
  let zrusitelne = new Set()
  /** Odpověď na odškrtnutí přepíše starší stav z dotazu, který běžel souběžně. */
  let verze = 0
  let nacitaSe = false
  let posledniNacteni = Date.now()

  function popisZazneni (zazneni) {
    if (!zazneni) {
      return ''
    }
    let text = 'Zaznělo v ' + zazneni.kdy + (zazneni.kdo ? ' · ' + zazneni.kdo : '')
    if (zazneni.zruseno) {
      text += '. Po bingu zrušeno v ' + zazneni.zruseno + (zazneni.zrusil ? ' · ' + zazneni.zrusil : '') +
        ', vrátit jde i s původním časem.'
    }
    return text
  }

  /** Stav tlačítka fráze, stejně jako první vykreslení v bingo.php. */
  function nastavTlacitko (tlacitko, trida) {
    const id = tlacitko.dataset.idFraze
    const zazneni = stav.zaznele[id]
    const zruseni = zazneni ? null : stav.zrusene[id]
    tlacitko.classList.toggle(trida + '--zaznelo', Boolean(zazneni))
    tlacitko.classList.toggle(trida + '--zruseno', Boolean(zruseni))
    tlacitko.value = id + '-' + (zazneni ? '0' : '1')
    tlacitko.title = popisZazneni(zazneni || zruseni)
    tlacitko.disabled = zazneni
      ? !zrusitelne.has(id)
      : (zruseni ? !stav.lzeVratit : !stav.lzeOdskrtnout)
  }

  function vykresliKartu () {
    if (!karta) {
      return
    }
    karta.querySelectorAll('[data-id-fraze]').forEach(function (pole) {
      nastavTlacitko(pole, 'bingo-pole')
    })
    const vRade = new Set()
    rady.forEach(function (rada) {
      const hotova = rada.every(function (pozice) {
        return pozice === volnePole || Boolean(stav.zaznele[frazeNaKarte.get(pozice)])
      })
      if (hotova) {
        rada.forEach(function (pozice) { vRade.add(pozice) })
      }
    })
    karta.querySelectorAll('[data-pozice]').forEach(function (pole) {
      pole.classList.toggle('bingo-pole--v-rade', vRade.has(Number(pole.dataset.pozice)))
    })
  }

  /**
   * Fráze z karet ostatních. Tlačítka, která ve hře zůstávají, zůstanou i na svém místě: nové načtení stavu tak
   * neukradne rozpracované klepnutí ani fokus z klávesnice.
   */
  function vykresliOstatni () {
    if (!ostatniSeznam) {
      return
    }
    const stavajici = new Map()
    ostatniSeznam.querySelectorAll('[data-id-fraze]').forEach(function (tlacitko) {
      stavajici.set(tlacitko.dataset.idFraze, tlacitko)
    })
    const tlacitka = []
    if (stav.stav !== 'priprava') {
      stav.frazeVeHre.forEach(function (fraze) {
        const id = String(fraze.id)
        if (idsNaKarte.has(id)) {
          return
        }
        let tlacitko = stavajici.get(id)
        if (tlacitko) {
          stavajici.delete(id)
        } else {
          tlacitko = document.createElement('button')
          tlacitko.type = 'submit'
          tlacitko.name = 'zmena'
          tlacitko.className = 'bingo-fraze'
          tlacitko.dataset.idFraze = id
          tlacitko.textContent = fraze.text
        }
        nastavTlacitko(tlacitko, 'bingo-fraze')
        tlacitka.push(tlacitko)
      })
    }
    stavajici.forEach(function (tlacitko) { tlacitko.remove() })
    // nová tlačítka vloží na místo podle abecedy, stávajících se nedotkne
    let dalsi = ostatniSeznam.querySelector('[data-id-fraze]')
    tlacitka.forEach(function (tlacitko) {
      if (tlacitko === dalsi) {
        dalsi = dalsi.nextElementSibling
      } else {
        ostatniSeznam.insertBefore(tlacitko, dalsi)
      }
    })
    ostatni.hidden = tlacitka.length === 0
  }

  /** Karta vítěze jen pro čtení: odškrtnuté fráze a vyznačená hotová řada. */
  function kartaViteze (vitez) {
    const obal = document.createElement('figure')
    obal.className = 'bingo-vitez'
    const jmeno = document.createElement('figcaption')
    jmeno.textContent = vitez.jmeno + ' (' + vitez.kdy + ')'
    const mrizka = document.createElement('div')
    mrizka.className = 'bingo-karta bingo-karta--vitezna'
    const vRade = new Set(vitez.rady)
    for (let pozice = 0; pozice < velikostKarty * velikostKarty; pozice++) {
      const pole = document.createElement('div')
      pole.className = 'bingo-pole'
      if (pozice === volnePole) {
        pole.textContent = 'Volné pole'
        pole.classList.add('bingo-pole--volne', 'bingo-pole--zaznelo')
      } else {
        pole.textContent = vitez.pole[pozice].text
        pole.classList.toggle('bingo-pole--zaznelo', Boolean(stav.zaznele[vitez.pole[pozice].id]))
      }
      if (vRade.has(pozice)) {
        pole.classList.add('bingo-pole--zaznelo', 'bingo-pole--v-rade')
      }
      mrizka.appendChild(pole)
    }
    obal.append(jmeno, mrizka)
    return obal
  }

  function podpisVitezu (nejakyStav) {
    return nejakyStav.vitezove.map(function (vitez) { return vitez.idUzivatele + '@' + vitez.kdy }).join(',')
  }

  function vykresliViteze (predchoziStav) {
    vitezoveKarty.replaceChildren(...stav.vitezove.map(kartaViteze))
    vitezove.hidden = stav.vitezove.length === 0
    // vyhlásí bingo, i když vrácené odškrtnutí změnilo vítěze ukončené soutěže
    const praveVyhral = stav.stav === 'vyhrano' && predchoziStav &&
      (predchoziStav.stav !== 'vyhrano' || podpisVitezu(predchoziStav) !== podpisVitezu(stav))
    if (!praveVyhral || typeof vyhlaseni.showModal !== 'function') {
      if (stav.stav !== 'vyhrano' && vyhlaseni.open) {
        vyhlaseni.close()
      }
      return
    }
    const vyhralJsem = stav.vitezove.some(function (vitez) { return vitez.idUzivatele === idUzivatele })
    const jmena = stav.vitezove.map(function (vitez) { return vitez.jmeno }).join(', ')
    vyhlaseni.querySelector('.bingo-vyhlaseni-text').textContent = vyhralJsem
      ? 'Tvoje karta vyhrála!' + (stav.vitezove.length > 1 ? ' Spolu s: ' + jmena : '')
      : (stav.vitezove.length === 1 ? 'Vítěz: ' : 'Vítězové: ') + jmena
    vyhlaseni.querySelector('.bingo-vyhlaseni-karty').replaceChildren(...stav.vitezove.map(kartaViteze))
    if (!vyhlaseni.open) {
      vyhlaseni.showModal()
    }
  }

  function vykresli (novyStav) {
    if (novyStav.idKarty !== idKarty) {
      // kartu mezitím vygeneroval jinde (jiné zařízení nebo záložka), stránka ukazuje jinou
      window.location.reload()
      return
    }
    const predchoziStav = stav === novyStav ? null : stav
    stav = novyStav
    zrusitelne = new Set(stav.lzeZrusit.map(String))
    popis.textContent = stav.popis
    if (spusteni) {
      spusteni.hidden = !stav.lzeSpustit
    }
    if (generovani) {
      generovani.hidden = !stav.lzeGenerovat
      if (!karta && stav.stav === 'bezi') {
        // stránka načtená před startem: kartu teď dostane jen jednu
        generovani.querySelector('button').dataset.potvrzeni =
          'Soutěž už běží: kartu dostaneš jen jednu a vyměnit ji nepůjde. Pokračovat?'
      }
    }
    vykresliKartu()
    vykresliOstatni()
    vykresliViteze(predchoziStav)
  }

  async function nactiStav () {
    if (nacitaSe || document.hidden) {
      return
    }
    nacitaSe = true
    posledniNacteni = Date.now()
    const verzePredDotazem = verze
    try {
      const odpoved = await fetch(urlStavu, { credentials: 'same-origin', cache: 'no-store' })
      if (odpoved.ok && verzePredDotazem === verze) {
        vykresli(await odpoved.json())
      }
    } catch (chyba) {
      // výpadek sítě, příště to vyjde
    } finally {
      nacitaSe = false
    }
  }

  function potvrzeniZmeny (idFraze) {
    const zruseni = stav.zrusene[idFraze]
    if (stav.zaznele[idFraze]) {
      return stav.stav === 'vyhrano'
        ? 'Zrušit odškrtnutí všem? Když tím zmizí bingo, soutěž poběží dál. Vrátit ho pak jde i s původním časem.'
        : 'Zrušit odškrtnutí všem?'
    }
    if (zruseni) {
      return 'Vrátit odškrtnutí z ' + zruseni.kdy + ', které se po bingu zrušilo? Platí původní čas a podle něj se ' +
        'znovu určí vítěz.'
    }
    return null
  }

  async function odskrtni (tlacitko) {
    const idFraze = tlacitko.dataset.idFraze
    const zaznelo = !stav.zaznele[idFraze]
    const potvrzeni = potvrzeniZmeny(idFraze)
    if (potvrzeni && !window.confirm(potvrzeni)) {
      return
    }
    verze++
    const mojeVerze = verze
    // hned ukáže, co bude, odpověď to opraví
    const zaznele = Object.assign({}, stav.zaznele)
    const zrusene = Object.assign({}, stav.zrusene)
    if (zaznelo) {
      // vrácené odškrtnutí má původní čas
      zaznele[idFraze] = zrusene[idFraze]
        ? { kdo: zrusene[idFraze].kdo, kdy: zrusene[idFraze].kdy }
        : { kdo: null, kdy: '…' }
      delete zrusene[idFraze]
    } else {
      delete zaznele[idFraze]
    }
    vykresli(Object.assign({}, stav, { zaznele: zaznele, zrusene: zrusene }))

    const data = new FormData()
    data.set('akce', 'odskrtnout')
    data.set('jednani', idJednani)
    data.set('zmena', idFraze + '-' + (zaznelo ? '1' : '0'))
    try {
      const odpoved = await fetch(urlAkci, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' },
      })
      const novyStav = await odpoved.json()
      if (novyStav.chyba) {
        window.alert(novyStav.chyba)
      }
      if (novyStav.zaznele && mojeVerze === verze) {
        vykresli(novyStav)
      }
    } catch (chyba) {
      window.alert('Odškrtnutí se nepovedlo uložit, zkus to znovu.')
      nactiStav()
    }
  }

  hra.addEventListener('click', function (event) {
    const tlacitko = event.target.closest('button[data-id-fraze]')
    if (!tlacitko || tlacitko.disabled) {
      return
    }
    event.preventDefault()
    odskrtni(tlacitko)
  })

  vykresli(stav)

  window.setInterval(function () {
    if (stav.sledovatZa && Date.now() - posledniNacteni >= stav.sledovatZa * 1000) {
      nactiStav()
    }
  }, 1000)
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && stav.sledovatZa) {
      nactiStav()
    }
  })
})()
