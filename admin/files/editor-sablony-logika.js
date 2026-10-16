/**
 * Pure logic of the e-mail text editor, free of the DOM so it can be tested.
 *
 * The rules for writing symbols and the error texts mirror PHP, and the server always decides;
 * this only lets the author learn about an error before the form is submitted.
 *
 * @see \Gamecon\Kanaly\EmailovaSablona\RozkladSymbolu
 * @see \Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaValidator
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory()
  } else {
    root.EditorSablonyLogika = factory()
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict'

  const TYP = {
    TEXT: 'TEXT',
    SYMBOL: 'SYMBOL',
    NEUZAVRENY_SYMBOL: 'NEUZAVRENY_SYMBOL',
    CISLICNY_SYMBOL: 'CISLICNY_SYMBOL',
  }

  /**
   * @param {string} text
   * @return {{typ: string, hodnota: string, zacatek: number, konec: number}[]} tokens; zacatek and konec are
   *     positions in the original text, including the percent signs
   */
  function rozloz(text) {
    const tokeny = []
    let rozpracovanyText = ''
    let zacatekTextu = 0
    let pozice = 0

    const ulozText = function (konec) {
      if (rozpracovanyText !== '') {
        tokeny.push({typ: TYP.TEXT, hodnota: rozpracovanyText, zacatek: zacatekTextu, konec: konec})
        rozpracovanyText = ''
      }
    }
    const pridejKText = function (retezec, odPozice) {
      if (rozpracovanyText === '') {
        zacatekTextu = odPozice
      }
      rozpracovanyText += retezec
    }

    while (pozice < text.length) {
      if (text[pozice] !== '%') {
        let dalsiProcento = text.indexOf('%', pozice)
        if (dalsiProcento === -1) {
          dalsiProcento = text.length
        }
        pridejKText(text.slice(pozice, dalsiProcento), pozice)
        pozice = dalsiProcento
        continue
      }

      const zaProcentem = text.slice(pozice + 1)

      if (zaProcentem.startsWith('%')) {
        pridejKText('%', pozice)
        pozice += 2
        continue
      }

      let shoda = /^([A-Za-z][A-Za-z0-9_]*)(%?)/.exec(zaProcentem)
      if (shoda) {
        ulozText(pozice)
        const delka = 1 + shoda[0].length
        tokeny.push({
          typ: shoda[2] === '%' ? TYP.SYMBOL : TYP.NEUZAVRENY_SYMBOL,
          hodnota: shoda[1],
          zacatek: pozice,
          konec: pozice + delka,
        })
        pozice += delka
        continue
      }

      shoda = /^([0-9]+)/.exec(zaProcentem)
      if (shoda) {
        ulozText(pozice)
        const delka = 1 + shoda[0].length
        tokeny.push({typ: TYP.CISLICNY_SYMBOL, hodnota: shoda[1], zacatek: pozice, konec: pozice + delka})
        pozice += delka
        continue
      }

      pridejKText('%', pozice)
      pozice += 1
    }
    ulozText(pozice)

    return tokeny
  }

  function jePromenna(nazev) {
    return /^[a-z][a-z0-9_]*$/.test(nazev)
  }

  function jeSystemovaHodnota(nazev) {
    return /^[A-Z][A-Z0-9_]*$/.test(nazev)
  }

  function jakoZapsano(token) {
    switch (token.typ) {
      case TYP.SYMBOL:
        return '%' + token.hodnota + '%'
      case TYP.NEUZAVRENY_SYMBOL:
      case TYP.CISLICNY_SYMBOL:
        return '%' + token.hodnota
      default:
        return token.hodnota
    }
  }

  /**
   * @typedef {{
   *   promenne: {nazev: string, popis: string, ukazka: string, vPredmetu: boolean}[],
   *   systemove: {nazev: string, popis: string, ukazka: string}[],
   *   neverejne: string[],
   * }} DataSymbolu
   */

  /**
   * @param {DataSymbolu} data
   * @param {string} misto 'Předmět' or 'Text'
   * @param {string} obsah
   * @param {boolean} jePredmet
   * @return {string[]}
   */
  function chybySymbolu(data, misto, obsah, jePredmet) {
    const chyby = []
    rozloz(obsah).forEach(function (token) {
      const chyba = chybaTokenu(data, token, jePredmet)
      if (chyba !== null) {
        chyby.push(misto + ': ' + chyba)
      }
    })
    const slozene = obsah.match(/\{[A-Za-z][A-Za-z0-9_]*\}/g) || []
    slozene.forEach(function (slozenyZapis) {
      chyby.push(misto + ': symboly se píšou mezi procenta, ne do složených závorek („' + slozenyZapis + '“).')
    })

    return chyby
  }

  function chybaTokenu(data, token, jePredmet) {
    const zapsano = jakoZapsano(token)
    switch (token.typ) {
      case TYP.NEUZAVRENY_SYMBOL:
        return 'Symbol „' + zapsano + '“ není zavřený, chybí mu závěrečné procento. Samotné procento napiš jako %%.'
      case TYP.CISLICNY_SYMBOL:
        return '„' + zapsano + '“ není symbol, symboly mají jména. Samotné procento napiš jako %%.'
      case TYP.SYMBOL:
        break
      default:
        return null
    }

    if (jePromenna(token.hodnota)) {
      const promenna = data.promenne.find(function (kandidat) {
        return kandidat.nazev === token.hodnota
      })
      if (!promenna) {
        return '„' + zapsano + '“ není v téhle šabloně k dispozici.'
      }
      if (jePredmet && !promenna.vPredmetu) {
        return '„' + zapsano + '“ nejde použít v předmětu, jen v textu.'
      }
      return null
    }

    if (jeSystemovaHodnota(token.hodnota)) {
      return chybaSystemoveHodnoty(data, token)
    }

    return '„' + zapsano + '“ má špatný tvar: proměnné se píšou malými písmeny, systémové hodnoty VELKÝMI.'
  }

  function chybaSystemoveHodnoty(data, token) {
    const zapsano = jakoZapsano(token)
    if (data.systemove.some(function (hodnota) {
      return hodnota.nazev === token.hodnota
    })) {
      return null
    }
    if (data.neverejne.indexOf(token.hodnota) !== -1) {
      return '„' + zapsano + '“ je neveřejné nastavení a do e-mailu se nedosadí. Zveřejnit ho jde na stránce Nastavení.'
    }

    return '„' + zapsano + '“ není známá systémová hodnota.'
  }

  /**
   * @param {DataSymbolu} data
   * @param {string} predmet
   * @param {string} text
   * @return {string[]} errors in Czech, an empty array means it can be saved
   */
  function validuj(data, predmet, text) {
    let chyby = []

    const orezanyPredmet = predmet.trim()
    if (orezanyPredmet === '') {
      chyby.push('Předmět nesmí být prázdný.')
    } else if (/[\r\n]/.test(orezanyPredmet)) {
      chyby.push('Předmět musí být na jednom řádku.')
    }
    if (text.trim() === '') {
      chyby.push('Text nesmí být prázdný.')
    }

    chyby = chyby.concat(chybySymbolu(data, 'Předmět', predmet, true))
    chyby = chyby.concat(chybySymbolu(data, 'Text', text, false))

    return chyby.filter(function (chyba, index) {
      return chyby.indexOf(chyba) === index
    })
  }

  /**
   * The symbol being typed right now: `%` or `%neco` right before the cursor, without the closing percent sign.
   *
   * @param {string} text
   * @param {number} kurzor
   * @return {{zacatek: number, predpona: string}|null}
   */
  function najdiRozepsanySymbol(text, kurzor) {
    const predKurzorem = text.slice(0, kurzor)
    const tokeny = rozloz(predKurzorem)
    const posledni = tokeny[tokeny.length - 1]
    if (!posledni || posledni.konec !== predKurzorem.length) {
      return null
    }

    if (posledni.typ === TYP.NEUZAVRENY_SYMBOL) {
      return {zacatek: posledni.zacatek, predpona: posledni.hodnota}
    }

    if (posledni.typ === TYP.TEXT) {
      // `%%` is a completed single percent sign, a lone `%` at the end only opens a symbol
      const zapsanyText = predKurzorem.slice(posledni.zacatek, posledni.konec)
      const procentNaKonci = zapsanyText.length - zapsanyText.replace(/%+$/, '').length
      if (procentNaKonci % 2 === 1) {
        return {zacatek: predKurzorem.length - 1, predpona: ''}
      }
    }

    return null
  }

  /**
   * What gets replaced on insert: the selection, and when it lies inside an already written `%symbol%`,
   * that whole symbol, so a symbol never nests inside another.
   *
   * @param {string} text
   * @param {number} zacatekVyberu
   * @param {number} konecVyberu
   * @return {{zacatek: number, konec: number}}
   */
  function rozsahKPrepsani(text, zacatekVyberu, konecVyberu) {
    const tokeny = rozloz(text)
    for (let index = 0; index < tokeny.length; index++) {
      const token = tokeny[index]
      if (token.typ !== TYP.SYMBOL && token.typ !== TYP.NEUZAVRENY_SYMBOL) {
        continue
      }
      // a cursor right before or right after a symbol does not belong to it, so insert next to it
      const zacatekUvnitr = zacatekVyberu > token.zacatek && zacatekVyberu < token.konec
      const konecUvnitr = konecVyberu > token.zacatek && konecVyberu < token.konec
      const vyberObsahujeSymbol = zacatekVyberu <= token.zacatek && konecVyberu >= token.konec
      if (vyberObsahujeSymbol) {
        continue
      }
      if (zacatekUvnitr || konecUvnitr) {
        return {
          zacatek: Math.min(zacatekVyberu, token.zacatek),
          konec: Math.max(konecVyberu, token.konec),
        }
      }
    }

    return {zacatek: zacatekVyberu, konec: konecVyberu}
  }

  /**
   * Symbols for the suggestion list given what is already typed. Those starting with it come first.
   *
   * @param {{nazev: string, popis: string}[]} symboly
   * @param {string} predpona
   * @return {{nazev: string, popis: string}[]}
   */
  function filtrujSymboly(symboly, predpona) {
    const hledane = predpona.toLowerCase()
    if (hledane === '') {
      return symboly.slice()
    }
    const zacinajici = []
    const obsahujici = []
    symboly.forEach(function (symbol) {
      const nazev = symbol.nazev.toLowerCase()
      if (nazev.indexOf(hledane) === 0) {
        zacinajici.push(symbol)
      } else if (nazev.indexOf(hledane) !== -1) {
        obsahujici.push(symbol)
      }
    })

    return zacinajici.concat(obsahujici)
  }

  return {
    TYP: TYP,
    rozloz: rozloz,
    jePromenna: jePromenna,
    jeSystemovaHodnota: jeSystemovaHodnota,
    jakoZapsano: jakoZapsano,
    validuj: validuj,
    najdiRozepsanySymbol: najdiRozepsanySymbol,
    rozsahKPrepsani: rozsahKPrepsani,
    filtrujSymboly: filtrujSymboly,
  }
}))
