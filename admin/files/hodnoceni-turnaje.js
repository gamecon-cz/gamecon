/**
 * Editor kritérií hodnocení turnaje (admin/scripts/modules/hodnoceni-turnaje.php): přidání, odebrání a přeřazení
 * kategorií a kritérií. Server dostane řádky v pořadí na stránce, nové bez id. Neuložené změny hlídá při odchodu.
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
  let pocitadlo = 0

  /** klíč nového řádku do názvů polí, jiný než klíče od serveru (i123, n1) */
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
      if (tlacitko.dataset.posun === '-1' && radek.previousElementSibling) {
        radek.previousElementSibling.before(radek)
      } else if (tlacitko.dataset.posun === '1' && radek.nextElementSibling) {
        radek.nextElementSibling.after(radek)
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
