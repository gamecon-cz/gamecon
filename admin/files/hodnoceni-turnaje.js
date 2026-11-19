/**
 * Editor kritérií hodnocení turnaje (admin/scripts/modules/hodnoceni-turnaje.php): přidání, odebrání a přeřazení
 * kategorií a kritérií, šipkou z kraje kategorie přesun kritéria do vedlejší. Server dostane řádky v pořadí na stránce,
 * nové bez id. Neuložené změny hlídá při odchodu.
 */
(function () {
  'use strict'

  const editor = document.querySelector('[data-editor-kriterii]')
  if (!editor) {
    return
  }

  const seznamKategorii = editor.querySelector('[data-seznam-kategorii]')
  const sablonaKategorie = document.querySelector('template[data-sablona-kategorie]')
  const sablonaKriteria = document.querySelector('template[data-sablona-kriteria]')
  // načtené ze souboru nebo neuložené po chybě je neuložené hned od začátku
  let neulozeno = editor.hasAttribute('data-neulozeno')
  // po chybě server vrátí odeslané řádky i s klíči j1, j2…, nový řádek nesmí dostat stejný, jinak by PHP jeho pole
  // spojilo s polem staršího řádku
  let pocitadlo = 0
  editor.querySelectorAll('input[name^="kategorie["]').forEach(function (pole) {
    for (const shoda of pole.name.matchAll(/\[j(\d+)\]/g)) {
      pocitadlo = Math.max(pocitadlo, Number(shoda[1]))
    }
  })

  /** klíč nového řádku do názvů polí, jiný než klíče od serveru (i123, n1) i od dřívějších nových řádků */
  function novyKlic () {
    pocitadlo += 1
    return 'j' + pocitadlo
  }

  function vytvor (sablona, klicKategorie, klicKriteria) {
    const obal = document.createElement('template')
    obal.innerHTML = sablona.innerHTML
      .split('__KATEGORIE__').join(klicKategorie)
      .split('__KRITERIUM__').join(klicKriteria)
      .trim()
    return obal.content.firstElementChild
  }

  /**
   * Kritérium s polem id (uložené zůstane uložené) do jiné kategorie: pole dostanou klíč té kategorie a nový klíč
   * kritéria, klíč n2 od serveru může v cílové kategorii už být.
   */
  function presunKriterium (kriterium, kategorie, naKonec) {
    const klic = novyKlic()
    kriterium.querySelectorAll('input[name]').forEach(function (pole) {
      pole.name = pole.name.replace(
        /^kategorie\[[^\]]+\]\[kriteria\]\[[^\]]+\]/,
        'kategorie[' + kategorie.dataset.klic + '][kriteria][' + klic + ']',
      )
    })
    const seznam = kategorie.querySelector('[data-seznam-kriterii]')
    if (naKonec) {
      seznam.append(kriterium)
    } else {
      seznam.prepend(kriterium)
    }
  }

  function pridejKriterium (kategorie) {
    const kriterium = vytvor(sablonaKriteria, kategorie.dataset.klic, novyKlic())
    kategorie.querySelector('[data-seznam-kriterii]').append(kriterium)
    kriterium.querySelector('input[type=text]').focus()
  }

  editor.addEventListener('click', function (event) {
    const tlacitko = event.target.closest('button[type=button]')
    if (!tlacitko || !editor.contains(tlacitko)) {
      return
    }
    if (tlacitko.matches('[data-pridat-kategorii]')) {
      const kategorie = vytvor(sablonaKategorie, novyKlic(), '')
      seznamKategorii.append(kategorie)
      pridejKriterium(kategorie)
      kategorie.querySelector('input[type=text]').focus()
    } else if (tlacitko.matches('[data-pridat-kriterium]')) {
      pridejKriterium(tlacitko.closest('[data-kategorie]'))
    } else if (tlacitko.matches('[data-posun]')) {
      // řádek kritéria leží uvnitř kategorie, nejbližší je ten, ke kterému tlačítko patří
      const radek = tlacitko.closest('[data-kriterium], [data-kategorie]')
      const nahoru = tlacitko.dataset.posun === '-1'
      const soused = nahoru
        ? radek.previousElementSibling
        : radek.nextElementSibling
      if (soused && nahoru) {
        soused.before(radek)
      } else if (soused) {
        soused.after(radek)
      } else if (radek.matches('[data-kriterium]')) {
        // z kraje kategorie do vedlejší: nahoru na konec předchozí, dolů na začátek další
        const kategorie = radek.closest('[data-kategorie]')
        const vedlejsi = nahoru
          ? kategorie.previousElementSibling
          : kategorie.nextElementSibling
        if (vedlejsi) {
          presunKriterium(radek, vedlejsi, nahoru)
        }
      }
      tlacitko.focus()
    } else if (tlacitko.matches('[data-odebrat]')) {
      tlacitko.closest('[data-kriterium], [data-kategorie]').remove()
    } else {
      return
    }
    neulozeno = true
  })

  editor.addEventListener('input', function () {
    neulozeno = true
  })

  editor.addEventListener('submit', function () {
    neulozeno = false
  })

  window.addEventListener('beforeunload', function (event) {
    if (neulozeno) {
      event.preventDefault()
      // starší prohlížeče se ptají jen s returnValue
      event.returnValue = ''
    }
  })
})()
